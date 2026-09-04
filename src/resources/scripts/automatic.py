#!/usr/bin/env python3
"""
Laser point detector v14 using Difference of Gaussians (DoG).
"""

import cv2
import numpy as np
import argparse
import itertools
import math
import json
import logging
import random
import time
import sys
import uuid
from pathlib import Path
from dataclasses import dataclass, asdict
from typing import List, Tuple, Optional, Dict

import scipy.spatial.distance


# ---------------------------------------------------------------------------
# Data classes
# ---------------------------------------------------------------------------

@dataclass
class ImageInfo:
    """COCO-style image entry."""
    id: int
    width: int
    height: int
    file_name: str


@dataclass
class Annotation:
    """COCO-style annotation."""
    image_id: int
    bbox: List[float]  # [x, y, width, height]
    score: float
    category_id: int
    id: str
    area: float


@dataclass
class Category:
    """COCO category."""
    id: int
    name: str


@dataclass
class Coco:
    """Top-level COCO structure."""
    images: List[ImageInfo]
    annotations: List[Annotation]
    categories: List[Category]


@dataclass
class OutputJson:
    """Output JSON format: mapping from image IDs to detected points."""
    points: Dict[str, List[List[int]]]


# ---------------------------------------------------------------------------
# Core detection engine (DoG-based)
# ---------------------------------------------------------------------------

def get_signal_response(img, channel_mode):
    """Calculates the specific channel response based on the chosen mode."""
    img_float = img.astype(np.float32)
    b, g, r = cv2.split(img_float)

    if channel_mode == "red":
        response = r - np.maximum(g, b)
    elif channel_mode == "green":
        response = g - np.maximum(r, b)
    elif channel_mode == "blue":
        response = b - np.maximum(r, g)
    else:  # grayscale fallback
        response = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY).astype(np.float32)

    response[response < 0] = 0
    return response


def detect_laser_points(image_path, num_points=3, num_candidates=50,
                        min_spread_ratio=0.02, max_spread_ratio=0.30,
                        channel_mode="red"):
    """
    Detects laser points using a highly robust Difference of Gaussians (DoG) method
    specifically tuned for faint, tiny spots underwater.
    """
    img = cv2.imread(str(image_path), cv2.IMREAD_COLOR | cv2.IMREAD_IGNORE_ORIENTATION)
    if img is None:
        return None, 0, 0, []

    height, width = img.shape[:2]
    max_dimension = max(height, width)

    # Physical bounds of the laser rig
    min_spread = max_dimension * min_spread_ratio
    max_spread = max_dimension * max_spread_ratio

    # 1. Calculate Signal Response
    signal = get_signal_response(img, channel_mode)

    # 2. Difference of Gaussians (DoG) Spot Filter
    signal_smooth = cv2.GaussianBlur(signal, (3, 3), 0)
    background = cv2.GaussianBlur(signal_smooth, (41, 41), 0)

    spot_response = signal_smooth - background
    spot_response[spot_response < 0] = 0

    cv2.normalize(spot_response, spot_response, 0, 255, cv2.NORM_MINMAX)
    spot_response = spot_response.astype(np.uint8)

    # 3. Find Candidate Peaks
    search_area = spot_response.copy()
    edge_margin = int(max_dimension * 0.02)
    search_area[0:edge_margin, :] = 0
    search_area[-edge_margin:, :] = 0
    search_area[:, 0:edge_margin] = 0
    search_area[:, -edge_margin:] = 0

    candidates = []
    mask_radius = int(max_dimension * 0.01)

    for _ in range(num_candidates):
        min_val, max_val, min_loc, max_loc = cv2.minMaxLoc(search_area)
        if max_val < 5:
            break
        candidates.append({'pt': max_loc, 'score': max_val})
        cv2.circle(search_area, max_loc, mask_radius, 0, -1)

    if len(candidates) < num_points:
        return img, width, height, []

    # 4. Geometric Triplet Scoring
    best_combo = None
    best_score = -1

    for combo in itertools.combinations(candidates, num_points):
        pts = [c['pt'] for c in combo]
        distances = []
        for i in range(num_points):
            for j in range(i + 1, num_points):
                distances.append(math.hypot(pts[i][0] - pts[j][0], pts[i][1] - pts[j][1]))

        if num_points > 1:
            combo_max_dist = max(distances)
            combo_min_dist = min(distances)
            valid_geometry = (combo_min_dist >= min_spread) and (combo_max_dist <= max_spread)
        else:
            valid_geometry = True

        if valid_geometry:
            score = sum(c['score'] for c in combo)
            if score > best_score:
                best_score = score
                best_combo = pts

    if best_combo is None:
        best_combo = [c['pt'] for c in candidates[:num_points]]

    return img, width, height, best_combo


def _pixel_channel_purity(img: np.ndarray, x: int, y: int, mode: str, radius: int = 2) -> float:
    """Estimate color purity around one candidate pixel in [0, 1]."""
    if mode not in {"red", "green", "blue"}:
        return 0.0

    h, w = img.shape[:2]
    x0, x1 = max(0, x - radius), min(w, x + radius + 1)
    y0, y1 = max(0, y - radius), min(h, y + radius + 1)
    patch = img[y0:y1, x0:x1].astype(np.float32)
    if patch.size == 0:
        return 0.0

    b, g, r = cv2.split(patch)
    if mode == "red":
        target = float(np.mean(r))
        other = float(np.mean(np.maximum(g, b)))
    elif mode == "green":
        target = float(np.mean(g))
        other = float(np.mean(np.maximum(r, b)))
    else:
        target = float(np.mean(b))
        other = float(np.mean(np.maximum(r, g)))

    purity = (target - other) / max(target, 1.0)
    return float(np.clip(purity, 0.0, 1.0))


def _channel_spot_score(img: np.ndarray, mode: str) -> float:
    """Score channel quality via DoG peak strength plus color purity priors."""
    signal = get_signal_response(img, mode)
    if signal.size == 0 or float(np.max(signal)) <= 0:
        return 0.0

    signal_smooth = cv2.GaussianBlur(signal, (3, 3), 0)
    background = cv2.GaussianBlur(signal_smooth, (41, 41), 0)
    spot = signal_smooth - background
    spot[spot < 0] = 0

    flat = spot.reshape(-1)
    if flat.size == 0:
        return 0.0

    top_k = min(24, flat.size)
    top_vals = np.partition(flat, -top_k)[-top_k:]
    base_score = float(np.mean(top_vals))

    if mode in {"red", "green", "blue"}:
        # Use hotspot purity as a tie-breaker to avoid bright but wrong channels.
        threshold = float(np.percentile(spot, 99.8))
        ys, xs = np.where(spot >= threshold)
        if len(xs) == 0:
            purity = 0.0
        else:
            sample_n = min(16, len(xs))
            purity_vals = []
            step = max(1, len(xs) // sample_n)
            for idx in range(0, len(xs), step):
                purity_vals.append(_pixel_channel_purity(img, int(xs[idx]), int(ys[idx]), mode))
                if len(purity_vals) >= sample_n:
                    break
            purity = float(np.mean(purity_vals)) if purity_vals else 0.0
        return base_score * (1.0 + 0.35 * purity)

    # Slightly downweight grayscale unless it is clearly superior.
    return base_score * 0.97


def determine_best_mode(image_paths, num_points=3, sample_size=100):
    """Determines the best color channel for DoG filtering. Subsamples if sample_size is set."""
    if not image_paths:
        return "red"

    modes = ["red", "green", "blue"]
    mode_scores = {m: 0 for m in modes}

    if sample_size and len(image_paths) > sample_size:
        sample_paths = random.sample(image_paths, sample_size)
    else:
        sample_paths = image_paths

    for path in sample_paths:
        img = cv2.imread(str(path))
        if img is None:
            continue

        for m in modes:
            mode_scores[m] += _channel_spot_score(img, m)

    best_mode = max(mode_scores, key=mode_scores.get)

    red_score = mode_scores["red"]
    green_score = mode_scores["green"]

    # If green is close to red, bias toward green for 2-point rigs.
    # This helps difficult green-laser datasets while preserving 3/4-point red rigs.
    if num_points <= 2 and green_score >= 0.70 * max(red_score, 1e-6):
        red_to_green = red_score / max(green_score, 1e-6)
        if red_to_green <= 1.70:
            best_mode = "green"

    # Guard against noisy blue selections unless blue is clearly dominant.
    if best_mode == "blue":
        best_rg = "red" if red_score >= green_score else "green"
        if mode_scores["blue"] < 1.40 * mode_scores[best_rg]:
            best_mode = best_rg

    return best_mode


# ---------------------------------------------------------------------------
# Channel mode resolution from color flags
# ---------------------------------------------------------------------------

def resolve_channel_mode(args, img_paths, sample_size=100):
    # If an explicit --channel was given and is not 'auto', use it directly
    if hasattr(args, 'channel') and args.channel and args.channel != "auto":
        return args.channel

    selected = []
    if args.red:
        selected.append("red")
    if args.green:
        selected.append("green")
    if args.blue:
        selected.append("blue")

    if len(selected) == 1:
        return selected[0]

    # Multiple or no colors selected -> auto-detect best channel
    expected_points = args.num_laserpoints if getattr(args, 'num_laserpoints', 0) > 0 else 2
    return determine_best_mode([str(p) for p in img_paths], num_points=expected_points, sample_size=sample_size)


# ---------------------------------------------------------------------------
# Image processing wrapper (produces COCO-compatible output)
# ---------------------------------------------------------------------------

def process_image(img_path: Path, args, channel_mode: str,
                  img_id: Optional[str] = None) -> Optional[Tuple[ImageInfo, List[Annotation], str, List[List[int]]]]:
    """Process a single image: finds points and converts to COCO format."""
    logger = logging.getLogger(__name__)
    logger.debug(f"Processing image: {img_path.name}")

    num_points = args.num_laserpoints if args.num_laserpoints > 0 else 2

    img, width, height, points = detect_laser_points(
        str(img_path),
        num_points=num_points,
        num_candidates=getattr(args, 'num_candidates', 50),
        min_spread_ratio=getattr(args, 'min_spread', 0.02),
        max_spread_ratio=getattr(args, 'max_spread', 0.30),
        channel_mode=channel_mode,
    )

    if img is None:
        logger.warning(f"Could not read or image is empty: {img_path}")
        return None

    # Check if we found the required number of points
    if (args.num_laserpoints > 0 and len(points) != args.num_laserpoints) or not points:
        return None

    # Generate IDs
    image_id_str = img_id if img_id else img_path.stem
    numerical_id = int(str(uuid.uuid4().int)[:10])

    image_info = ImageInfo(
        id=numerical_id,
        width=width,
        height=height,
        file_name=img_path.name,
    )

    annotations = []
    for point in points:
        annotations.append(Annotation(
            image_id=numerical_id,
            bbox=[float(point[0]), float(point[1]), 1.0, 1.0],
            score=1.0,
            category_id=0,
            id=str(uuid.uuid4()),
            area=1.0,
        ))

    simple_points = [[int(p[0]), int(p[1])] for p in points]
    return image_info, annotations, image_id_str, simple_points


# ---------------------------------------------------------------------------
# Area calculation helpers
# ---------------------------------------------------------------------------

def _compute_area(laserpoints: np.ndarray, width: int, height: int,
                  laserdistance: float, method_name: str) -> Optional[dict]:
    """
    Compute the image area (in m²) from detected laser points.
    Returns a JSON-serialisable dict
    """
    n = laserpoints.shape[0]
    laserdist = float(laserdistance) / 100.0

    if n == 4:
        dists = scipy.spatial.distance.pdist(laserpoints)
        dists.sort()
        apx = np.mean(dists[0:4]) ** 2
        if apx == 0:
            return {"error": True, "message": "Computed pixel area is zero.", "method": method_name}
        aqm = laserdist ** 2 * (float(width) * float(height)) / apx

    elif n == 3:
        a = np.sqrt((float(laserpoints[0, 0]) - float(laserpoints[1, 0])) ** 2 +
                     (float(laserpoints[0, 1]) - float(laserpoints[1, 1])) ** 2)
        b = np.sqrt((float(laserpoints[1, 0]) - float(laserpoints[2, 0])) ** 2 +
                     (float(laserpoints[1, 1]) - float(laserpoints[2, 1])) ** 2)
        c = np.sqrt((float(laserpoints[0, 0]) - float(laserpoints[2, 0])) ** 2 +
                     (float(laserpoints[0, 1]) - float(laserpoints[2, 1])) ** 2)
        s_real = 1.5 * laserdist
        are = np.sqrt(s_real * np.power(s_real - laserdist, 3))
        s_px = (a + b + c) / 2.0
        sqrtinp = s_px * (s_px - a) * (s_px - b) * (s_px - c)
        if sqrtinp < 0:
            return {"error": True, "message": "Computed pixel area is invalid.", "method": method_name}
        apx = np.sqrt(sqrtinp)
        if apx == 0:
            return {"error": True, "message": "Computed pixel area is zero.", "method": method_name}
        aqm = are * (float(width) * float(height)) / apx

    elif n == 2:
        a = np.sqrt((float(laserpoints[0, 0]) - float(laserpoints[1, 0])) ** 2 +
                     (float(laserpoints[0, 1]) - float(laserpoints[1, 1])) ** 2)
        flen = laserdist
        aqm = (flen * width) / a * (flen * height) / a

    else:
        return {"error": True, "message": "Unsupported number of laserpoints.", "method": method_name}

    if aqm <= 0:
        return {
            "error": True,
            "message": "The estimated image area is too small (was {} sqm).".format(round(aqm)),
            "method": method_name,
        }
    elif aqm > 50:
        return {
            "error": True,
            "message": "The estimated image area is too large (max is 50 sqm but was {} sqm).".format(round(aqm)),
            "method": method_name,
        }

    return {
        "error": False,
        "area": aqm,
        "count": n,
        "method": method_name,
        "points": laserpoints.tolist(),
    }


# ---------------------------------------------------------------------------
# Mode runners
# ---------------------------------------------------------------------------



def run_lpcolor_mode(args, channel_mode: str):
    """Mode: Write the determined color channel to a file."""
    logger = logging.getLogger(__name__)
    logger.info("=== MODE: LPColor ===")

    color_path = args.output / args.color_file
    with open(color_path, 'w') as f:
        f.write(channel_mode + "\n")
    logger.info(f"Saved color '{channel_mode}' to: {color_path}")


def run_detect_without_lines_mode(args, img_paths: List[Path],
                                  img_id_map: Optional[Dict[str, Path]],
                                  channel_mode: str):
    """Mode C: Detect laser points without any line constraints."""
    logger = logging.getLogger(__name__)
    logger.info("=== MODE C: Laser Point Detection (No Line Constraints) ===")
    logger.info(f"Processing {len(img_paths)} images...")

    results = []
    json_results = {}

    for img_path in img_paths:
        img_id = None
        if img_id_map:
            for id_key, path_val in img_id_map.items():
                if path_val.resolve() == img_path.resolve():
                    img_id = id_key
                    break

        result = process_image(img_path, args, channel_mode, img_id=img_id)
        if result:
            img_info, annotations, image_id, simple_points = result
            results.append((img_info, annotations))
            json_results[image_id] = simple_points

    _write_outputs(args, results, json_results)


def run_biigle_mode(args, img_paths: List[Path], img_id_map: Optional[Dict[str, Path]],
                    channel_mode: str):
    """Mode D: Detect laser points without line constraints – output JSON to stdout."""
    detection = "dog_detector (biigle_mode)"

    result = process_image(img_paths[0], args, channel_mode, img_id=None)
    if result:
        image_info, _, _, simple_points = result
        laserpoints = np.array(simple_points)

        area_result = _compute_area(laserpoints, image_info.width, image_info.height,
                                    args.laserdistance, detection)
        area_result["channel_mode"] = channel_mode
        print(json.dumps(area_result))
    else:
        print(json.dumps({
            "error": True,
            "message": "No laserpoints could be detected.",
            "method": detection,
        }))


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

def _write_outputs(args, results: List[Tuple[ImageInfo, List[Annotation]]],
                   json_results: Dict[str, List[List[int]]]):
    """Write COCO and JSON outputs"""
    logger = logging.getLogger(__name__)

    coco = Coco(
        images=[],
        annotations=[],
        categories=[Category(id=0, name="Laser Point")],
    )

    point_counter = 0
    for img_info, annotations in results:
        coco.images.append(img_info)
        coco.annotations.extend(annotations)
        point_counter += len(annotations)

    output_json_path = args.output / args.json_file_name
    coco_dict = {
        "images": [asdict(img) for img in coco.images],
        "annotations": [asdict(ann) for ann in coco.annotations],
        "categories": [asdict(cat) for cat in coco.categories],
    }

    with open(output_json_path, 'w') as f:
        json.dump(coco_dict, f, indent=2)

    if args.output_json:
        output_json = OutputJson(points=json_results)
        with open(args.output_json, 'w') as f:
            json.dump(asdict(output_json), f, indent=2)
        logger.info(f"Successfully wrote JSON points to: {args.output_json}")

    logger.info(f"Successfully wrote COCO results to: {output_json_path}")
    logger.info(f"Total points found: {point_counter}")


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Find laser points using Difference of Gaussians (DoG). "
    )

    # Input/output arguments
    parser.add_argument("-i", "--input", nargs="+", type=Path,
                        help="Path to input image(s) or directory")
    parser.add_argument("--input-json", type=Path,
                        help="JSON file containing mapping from image IDs to file paths")
    parser.add_argument("-o", "--output", type=Path, default=Path("output"),
                        help="Output directory for the results")
    parser.add_argument("--json-file-name", default="coco.json",
                        help="File name of the COCO output file")
    parser.add_argument("--output-json", type=Path,
                        help="Output JSON file with image ID to points mapping")

    # Detection parameters
    parser.add_argument("--num-laserpoints", type=int, default=0,
                        help="The number of laser points to search for (0 = auto/2)")

    # Color selection
    parser.add_argument("--red", action="store_true",
                        help="Search only for red laser points")
    parser.add_argument("--green", action="store_true",
                        help="Search only for green laser points")
    parser.add_argument("--blue", action="store_true",
                        help="Search only for blue laser points")

    # DoG-specific tuning parameters
    parser.add_argument("--num-candidates", type=int, default=50,
                        help="Number of candidate spots to evaluate")
    parser.add_argument("--min-spread", type=float, default=0.02,
                        help="Min distance ratio between laser points")
    parser.add_argument("--max-spread", type=float, default=0.30,
                        help="Max distance ratio between laser points")
    parser.add_argument("--channel", type=str, default="auto",
                        choices=["auto", "red", "green", "blue", "gray"],
                        help="Force a specific channel mode (overrides --red/--green/--blue)")

    # Mode selection
    parser.add_argument("--mode",
                        choices=["detect-without-lines", "biigle_mode", "lpcolor"],
                        default="detect-without-lines", help="Run mode")
    parser.add_argument("--color-file", default="color.txt",
                        help="File to save/load detected color")
    parser.add_argument("--laserdistance", type=float, default=50.0,
                        help="Distance of the laser in cm, used for area estimation in biigle mode")

    # Logging
    parser.add_argument("--verbose", "-v", action="store_true",
                        help="Enable verbose logging")

    args = parser.parse_args()

    # Set up logging
    log_level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=log_level,
        format='%(asctime)s - %(levelname)s - %(message)s',
    )
    logger = logging.getLogger(__name__)

    start_time = time.time()

    # Validate input arguments
    if not args.input and not args.input_json:
        parser.error("Either --input or --input-json must be provided")

    if args.input and args.input_json:
        parser.error("Cannot specify both --input and --input-json")



    # Create output directory
    args.output.mkdir(parents=True, exist_ok=True)

    # Process input – either from file paths or JSON mapping
    img_paths: List[Path] = []
    img_id_map: Optional[Dict[str, Path]] = None

    if args.input:
        for path in args.input:
            if path.is_dir():
                for ext in ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff']:
                    img_paths.extend(path.glob(f"*.{ext}"))
                    img_paths.extend(path.glob(f"*.{ext.upper()}"))
            elif path.is_file():
                img_paths.append(path)
    elif args.input_json:
        with open(args.input_json, 'r') as f:
            input_json = json.load(f)

        img_id_map = {}
        for img_id, file_path in input_json.items():
            path = Path(file_path)
            if path.exists() and path.is_file():
                # Must not check file extensions here because the BIIGLE file cache does
                # not use extensions.
                img_paths.append(path)
                img_id_map[img_id] = path
            else:
                logger.warning(f"File not found or not accessible: {file_path}")

    logger.info(f"Found {len(img_paths)} images to process.")

    # Resolve channel mode
    sample_size = None if args.mode == "lpcolor" else 100
    channel_mode = resolve_channel_mode(args, img_paths, sample_size=sample_size)
    logger.info(f"Using channel mode: {channel_mode}")

    # Execute based on mode
    try:
        if args.mode == "detect-without-lines":
            run_detect_without_lines_mode(args, img_paths, img_id_map, channel_mode)
        elif args.mode == "biigle_mode":
            run_biigle_mode(args, img_paths, img_id_map, channel_mode)
        elif args.mode == "lpcolor":
            run_lpcolor_mode(args, channel_mode)
        else:
            parser.error(f"Invalid mode: {args.mode}")
    except Exception as e:
        logger.error(f"Error during execution: {e}")
        sys.exit(1)

    execution_time = time.time() - start_time
    logger.info(f"Total execution time: {execution_time:.2f} seconds")


if __name__ == "__main__":
    main()