#!/usr/bin/env python3
"""
Python implementation of the Rust laser point detector
A program to find laser points in images using color thresholds.
"""

import argparse
import json
import logging
import random
import time
from pathlib import Path
from typing import Dict, List, Optional, Tuple, Union
import uuid
import os
import sys

import cv2
import numpy as np
import multiprocessing as mp
from concurrent.futures import ProcessPoolExecutor, as_completed
from dataclasses import dataclass, asdict
import scipy.spatial.distance

# Machine learning imports for clustering and line fitting
try:
    from sklearn.cluster import DBSCAN
    from sklearn.linear_model import LinearRegression
    HAS_SKLEARN = True
except ImportError:
    HAS_SKLEARN = False
    print("Warning: scikit-learn not found. Line fitting functionality will be disabled.")


@dataclass
class LineParams:
    """Parameters of a fitted line."""
    slope: float
    intercept: float


@dataclass
class LinesFile:
    """Structure for saving/loading line parameters."""
    lines: List[LineParams]


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
class ImageInfo:
    """COCO-style image entry."""
    id: int
    width: int
    height: int
    file_name: str


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
class InputJson:
    """Input JSON format: mapping from image IDs to file paths."""
    images: Dict[str, str]


@dataclass
class OutputJson:
    """Output JSON format: mapping from image IDs to detected points."""
    points: Dict[str, List[List[int]]]


class LaserPointDetector:
    """Main laser point detection class."""
    
    def __init__(self, args):
        self.args = args
        self.logger = logging.getLogger(__name__)
        
    def find_lps(self, image: np.ndarray, colors_to_search: List[int], 
                 img_name: str, mask: Optional[np.ndarray] = None) -> Tuple[List[Tuple[int, int]], int]:
        """
        Finds laser points (LPs) in a given image.
        
        Returns:
            Tuple of (points_list, best_color_channel)
        """
        num_lines_to_find = 2 if self.args.num_lines == 0 else self.args.num_lines
        
        # Apply mask if provided
        if mask is not None:
            final_image = cv2.bitwise_and(image, image, mask=mask)
        else:
            final_image = image.copy()
            
        best_points = []
        best_color = 0
        final_threshold = 0
        max_point_area = 600.0
        
        for color_channel in colors_to_search:
            self.logger.debug(f"[{img_name}] Processing color channel: {color_channel}")
            
            # Extract single channel
            if self.args.use_lab:
                lab_image = cv2.cvtColor(final_image, cv2.COLOR_BGR2LAB)
                if color_channel == 0:  # Red in LAB (A channel)
                    single_channel = lab_image[:, :, 1]
                elif color_channel == 1:  # Green in LAB (inverted A channel)
                    single_channel = 255 - lab_image[:, :, 1]
                elif color_channel == 2:  # Blue in LAB (B channel)
                    single_channel = lab_image[:, :, 2]
                else:
                    continue
            else:
                if color_channel == 0:  # Red
                    single_channel = final_image[:, :, 2]
                elif color_channel == 1:  # Green
                    single_channel = final_image[:, :, 1]
                elif color_channel == 2:  # Blue
                    single_channel = final_image[:, :, 0]
                else:
                    continue
            
            # Apply Gaussian blur if specified
            if self.args.gaussian_blur > 0:
                ksize = self.args.gaussian_blur
                single_channel = cv2.GaussianBlur(single_channel, (ksize, ksize), 0)
            
            points_found_this_channel = []
            threshold_found_at = 0
            current_threshold = self.args.start_threshold
            
            while len(points_found_this_channel) < num_lines_to_find and current_threshold > self.args.min_threshold:
                # Apply threshold
                _, thresholded = cv2.threshold(single_channel, current_threshold, 255, cv2.THRESH_BINARY)
                
                # Apply morphological opening if specified
                if self.args.morph_open > 0:
                    ksize = self.args.morph_open
                    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (ksize, ksize))
                    thresholded = cv2.morphologyEx(thresholded, cv2.MORPH_OPEN, kernel)
                
                if self.args.use_blobdetector:
                    points_found_this_channel = self._detect_blobs(thresholded, max_point_area)
                else:
                    points_found_this_channel = self._detect_contours(thresholded, max_point_area)
                
                if len(points_found_this_channel) >= num_lines_to_find:
                    threshold_found_at = current_threshold
                    break
                current_threshold -= 5
            
            if len(points_found_this_channel) > len(best_points):
                best_points = points_found_this_channel
                best_color = color_channel
                final_threshold = threshold_found_at
        
        # Draw debug images if requested
        if self.args.draw and best_points:
            self._draw_debug_images(image, best_points, best_color, final_threshold, img_name)
        
        return best_points, best_color
    
    def _detect_blobs(self, thresholded: np.ndarray, max_point_area: float) -> List[Tuple[int, int]]:
        """Detect points using SimpleBlobDetector."""
        # Set up blob detector parameters
        params = cv2.SimpleBlobDetector_Params()
        params.filterByColor = True
        params.blobColor = 255
        params.filterByArea = True
        params.minArea = self.args.min_point_area
        params.maxArea = max_point_area
        params.filterByCircularity = self.args.filter_by_circularity
        params.minCircularity = self.args.min_circularity
        params.filterByConvexity = self.args.filter_by_convexity
        params.minConvexity = self.args.min_convexity
        params.filterByInertia = self.args.filter_by_inertia
        params.minInertiaRatio = self.args.min_inertia_ratio
        params.maxInertiaRatio = self.args.max_inertia_ratio
        
        detector = cv2.SimpleBlobDetector_create(params)
        keypoints = detector.detect(thresholded)
        
        return [(int(kp.pt[0]), int(kp.pt[1])) for kp in keypoints]
    
    def _detect_contours(self, thresholded: np.ndarray, max_point_area: float) -> List[Tuple[int, int]]:
        """Detect points using contour finding."""
        contours, _ = cv2.findContours(thresholded, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
        
        points = []
        for contour in contours:
            area = cv2.contourArea(contour)
            if self.args.min_point_area < area <= max_point_area:
                # Calculate centroid
                M = cv2.moments(contour)
                if M["m00"] != 0:
                    cx = int(M["m10"] / M["m00"])
                    cy = int(M["m01"] / M["m00"])
                    points.append((cx, cy))
        
        return points
    
    def _draw_debug_images(self, image: np.ndarray, points: List[Tuple[int, int]], 
                          best_color: int, threshold: int, img_name: str):
        """Draw debug images with detected points."""
        # Extract the best channel
        if self.args.use_lab:
            lab_image = cv2.cvtColor(image, cv2.COLOR_BGR2LAB)
            if best_color == 0:
                best_channel = lab_image[:, :, 1]
            elif best_color == 1:
                best_channel = 255 - lab_image[:, :, 1]
            elif best_color == 2:
                best_channel = lab_image[:, :, 2]
            else:
                best_channel = np.zeros((image.shape[0], image.shape[1]), dtype=np.uint8)
        else:
            if best_color == 0:
                best_channel = image[:, :, 2]
            elif best_color == 1:
                best_channel = image[:, :, 1]
            elif best_color == 2:
                best_channel = image[:, :, 0]
            else:
                best_channel = np.zeros((image.shape[0], image.shape[1]), dtype=np.uint8)
        
        # Create thresholded debug image
        _, thresh_final = cv2.threshold(best_channel, threshold, 255, cv2.THRESH_BINARY)
        thresh_bgr = cv2.cvtColor(thresh_final, cv2.COLOR_GRAY2BGR)
        
        # Draw circles on thresholded image
        for point in points:
            cv2.circle(thresh_bgr, point, 15, (0, 0, 255), 2)
        
        # Save thresholded image
        thresh_path = self.args.output / f"{img_name}_thresh.webp"
        cv2.imwrite(str(thresh_path), thresh_bgr)
        
        # Draw circles on original image
        orig_bgr = image.copy()
        for point in points:
            cv2.circle(orig_bgr, point, 15, (0, 0, 255), 2)
        
        # Save original with annotations
        orig_path = self.args.output / f"{img_name}_orig.webp"
        cv2.imwrite(str(orig_path), orig_bgr)


def fit_lines_to_points(points: List[Tuple[int, int]]) -> List[LineParams]:
    """
    Takes a cloud of points, clusters them, and fits lines to the clusters.
    """
    if not HAS_SKLEARN:
        logging.warning("scikit-learn not available. Cannot fit lines.")
        return []
    
    if len(points) < 20:
        logging.warning("Not enough points found across all images to fit lines; skipping.")
        return []
    
    # Convert points to numpy array
    data = np.array(points)
    
    # Apply DBSCAN clustering
    clustering = DBSCAN(eps=40.0, min_samples=10).fit(data)
    labels = clustering.labels_
    
    line_params = []
    
    # For each cluster, fit a line
    unique_labels = set(labels)
    for label in unique_labels:
        if label == -1:  # Skip noise points
            continue
        
        cluster_mask = labels == label
        cluster_points = data[cluster_mask]
        
        if len(cluster_points) < 10:
            continue
        
        # Fit linear regression
        X = cluster_points[:, 0].reshape(-1, 1)  # x coordinates
        y = cluster_points[:, 1]  # y coordinates
        
        reg = LinearRegression().fit(X, y)
        
        line_params.append(LineParams(
            slope=reg.coef_[0],
            intercept=reg.intercept_
        ))
    
    return line_params


def subsample_images(img_paths: List[Path], subsample_size: int) -> List[Path]:
    """Randomly subsample images."""
    if len(img_paths) <= subsample_size:
        return img_paths
    
    return random.sample(img_paths, subsample_size)


def save_lines(lines: List[LineParams], file_path: str):
    """Save line parameters to a JSON file."""
    lines_file = LinesFile(lines=lines)
    
    # Convert dataclass to dict for JSON serialization
    lines_dict = {
        "lines": [{"slope": line.slope, "intercept": line.intercept} for line in lines]
    }
    
    with open(file_path, 'w') as f:
        json.dump(lines_dict, f, indent=2)


def load_lines(file_path: str) -> List[LineParams]:
    """Load line parameters from a JSON file."""
    with open(file_path, 'r') as f:
        data = json.load(f)
    
    return [LineParams(slope=line["slope"], intercept=line["intercept"]) 
            for line in data["lines"]]


def process_image(img_path: Path, args, colors_to_search: List[int], 
                 fitted_lines: Optional[List[LineParams]] = None,
                 img_id: Optional[str] = None) -> Optional[Tuple[ImageInfo, List[Annotation], str, List[List[int]]]]:
    """Process a single image: finds points and converts to COCO format."""
    logger = logging.getLogger(__name__)
    logger.debug(f"Processing image: {img_path.name}")
    
    # Read image
    image = cv2.imread(str(img_path))
    if image is None:
        logger.warning(f"Could not read or image is empty: {img_path}")
        return None
    
    # Create mask if fitted lines are provided
    mask = None
    if fitted_lines:
        mask = np.zeros((image.shape[0], image.shape[1]), dtype=np.uint8)
        for line in fitted_lines:
            # Draw line on mask
            p1 = (0, int(line.intercept))
            p2 = (image.shape[1], int(line.slope * image.shape[1] + line.intercept))
            cv2.line(mask, p1, p2, 255, 80)
    
    # Detect laser points
    detector = LaserPointDetector(args)
    img_name = img_path.stem
    points, _ = detector.find_lps(image, colors_to_search, img_name, mask)
    
    # Check if we found the required number of points
    if (args.num_lines > 0 and len(points) != args.num_lines) or not points:
        return None
    
    # Generate IDs
    image_id_str = img_id if img_id else img_name
    numerical_id = int(str(uuid.uuid4().int)[:10])  # Truncate for reasonable size
    
    # Create image info
    image_info = ImageInfo(
        id=numerical_id,
        width=image.shape[1],
        height=image.shape[0],
        file_name=img_path.name
    )
    
    # Create annotations
    annotations = []
    for point in points:
        annotations.append(Annotation(
            image_id=numerical_id,
            bbox=[float(point[0]), float(point[1]), 1.0, 1.0],
            score=1.0,
            category_id=0,
            id=str(uuid.uuid4()),
            area=1.0
        ))
    
    # Convert points to simple format for JSON output
    simple_points = [[int(p[0]), int(p[1])] for p in points]
    
    return image_info, annotations, image_id_str, simple_points


def run_lines_mode(args, img_paths: List[Path], colors_to_search: List[int]):
    """Mode A: Find lines from subsampled images."""
    logger = logging.getLogger(__name__)
    logger.info("=== MODE A: Line Fitting ===")
    
    if not HAS_SKLEARN:
        raise RuntimeError("scikit-learn is required for line fitting mode. Please install it with: pip install scikit-learn")
    
    # Subsample images
    subsample_paths = subsample_images(img_paths, args.subsample_size)
    logger.info(f"Subsampled {len(subsample_paths)} images from {len(img_paths)} total images for line fitting.")
    
    all_points = []
    detector = LaserPointDetector(args)
    
    # Process subsampled images
    for img_path in subsample_paths:
        image = cv2.imread(str(img_path))
        if image is not None:
            img_name = img_path.stem
            points, _ = detector.find_lps(image, colors_to_search, img_name)
            all_points.extend(points)
    
    logger.info(f"Found {len(all_points)} total points from subsampled images. Fitting lines...")
    lines = fit_lines_to_points(all_points)
    logger.info(f"Found {len(lines)} lines from point cloud.")
    
    # Save lines to file
    lines_path = args.output / args.lines_file
    save_lines(lines, str(lines_path))
    logger.info(f"Saved fitted lines to: {lines_path}")


def run_detect_mode(args, img_paths: List[Path], img_id_map: Optional[Dict[str, Path]], 
                   colors_to_search: List[int]):
    """Mode B: Detect laser points using pre-fitted lines."""
    logger = logging.getLogger(__name__)
    logger.info("=== MODE B: Laser Point Detection ===")
    
    # Load lines from file
    if args.mode == "detect-only":
        lines_path = Path(args.lines_file)
    else:
        lines_path = args.output / args.lines_file
    
    fitted_lines = load_lines(str(lines_path))
    logger.info(f"Loaded {len(fitted_lines)} fitted lines from: {lines_path}")
    
    logger.info(f"Processing {len(img_paths)} images for laser point detection...")
    
    results = []
    json_results = {}
    
    # Process images
    for img_path in img_paths:
        # Get image ID if using JSON input
        img_id = None
        if img_id_map:
            for id_key, path_val in img_id_map.items():
                if path_val.resolve() == img_path.resolve():
                    img_id = id_key
                    break
        
        result = process_image(img_path, args, colors_to_search, fitted_lines, img_id)
        if result:
            img_info, annotations, image_id, simple_points = result
            results.append((img_info, annotations))
            json_results[image_id] = simple_points
    
    _write_outputs(args, results, json_results)


def run_detect_without_lines_mode(args, img_paths: List[Path], img_id_map: Optional[Dict[str, Path]], 
                                 colors_to_search: List[int]):
    """Mode C: Detect laser points without any line constraints."""
    logger = logging.getLogger(__name__)
    logger.info("=== MODE C: Laser Point Detection (No Line Constraints) ===")
    
    logger.info(f"Processing {len(img_paths)} images for laser point detection without line constraints...")
    
    results = []
    json_results = {}
    
    # Process images
    for img_path in img_paths:
        # Get image ID if using JSON input
        img_id = None
        if img_id_map:
            for id_key, path_val in img_id_map.items():
                if path_val.resolve() == img_path.resolve():
                    img_id = id_key
                    break
        
        result = process_image(img_path, args, colors_to_search, None, img_id)
        if result:
            img_info, annotations, image_id, simple_points = result
            results.append((img_info, annotations))
            json_results[image_id] = simple_points
    
    _write_outputs(args, results, json_results)

def run_biigle_mode(args, img_paths: List[Path], img_id_map: Optional[Dict[str, Path]], 
                                 colors_to_search: List[int]):
    """Mode D: Detect laser points without any line constraints and output to stdout"""
    
    # Process images
    result = process_image(img_paths[0], args, colors_to_search, None, None)
    if result:
        image_info,_,_, simple_points = result
        width = image_info.width
        height = image_info.height
        detection = "color_threshold (biigle_mode)"
        laserpoints = np.array(simple_points)

        if laserpoints.shape[0] == 4:
            dists = scipy.spatial.distance.pdist(laserpoints)
            dists.sort()
            laserdist = float(args.laserdistance) / 100.
            apx = np.mean(dists[0:4])**2

            if apx == 0:
                print(json.dumps({
                    "error": True,
                    "message": "Computed pixel area is zero.",
                    "method": detection
                }))
                exit(1)
            aqm = laserdist**2 * (float(width) * float(height)) / apx
        elif laserpoints.shape[0] == 3:
            a = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[1, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[1, 1]), 2))
            b = np.sqrt(np.power(float(laserpoints[1, 0]) - float(laserpoints[2, 0]), 2) + np.power(float(laserpoints[1, 1]) - float(laserpoints[2, 1]), 2))
            c = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[2, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[2, 1]), 2))
            laserdist = float(args.laserdistance) / 100.
            s = 1.5 * laserdist
            are = np.sqrt(s * np.power(s - laserdist, 3))
            s = (a + b + c) / 2.
            sqrtinp = s * (s - a) * (s - b) * (s - c)
            if sqrtinp < 0:
                print(json.dumps({
                    "error": True,
                    "message": "Computed pixel area is invalid.",
                    "method": detection
                }))
                exit(1)
            apx = np.sqrt(sqrtinp)
            if apx == 0:
                print(json.dumps({
                    "error": True,
                    "message": "Computed pixel area is zero.",
                    "method": detection
                }))
                exit(1)
            aqm = are * (float(width) * float(height)) / apx
        elif laserpoints.shape[0] == 2:
            a = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[1, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[1, 1]), 2))
            flen = float(args.laserdistance) / 100.
            aqm = (flen * width) / a * (flen * height) / a
        else:
            # actually this should never happen
            print(json.dumps({
                "error": True,
                "message": "Unsupported number of laserpoints.",
                "method": detection
            }))
            exit(1)
        if (aqm <= 0):
            print(json.dumps({
                "error": True,
                "message": "The estimated image area is too small (was {} sqm).".format(round(aqm)),
                "method": detection,
            }))
            exit(1)
        elif (aqm > 50):
            print(json.dumps({
                "error": True,
                "message": "The estimated image area is too large (max is 50 sqm but was {} sqm).".format(round(aqm)),
                "method": detection
            }))
            exit(1)

        print(json.dumps({
            "error": False,
            "area": aqm,
            "count": laserpoints.shape[0],
            "method": detection,
            "points": laserpoints.tolist()
        }))


def _write_outputs(args, results: List[Tuple[ImageInfo, List[Annotation]]], 
                  json_results: Dict[str, List[List[int]]]):
    """Write COCO and JSON outputs."""
    logger = logging.getLogger(__name__)
    
    # Create COCO structure
    coco = Coco(
        images=[],
        annotations=[],
        categories=[Category(id=0, name="Laser Point")]
    )
    
    point_counter = 0
    for img_info, annotations in results:
        coco.images.append(img_info)
        coco.annotations.extend(annotations)
        point_counter += len(annotations)
    
    # Write COCO format output
    output_json_path = args.output / args.json_file_name
    coco_dict = {
        "images": [asdict(img) for img in coco.images],
        "annotations": [asdict(ann) for ann in coco.annotations],
        "categories": [asdict(cat) for cat in coco.categories]
    }
    
    with open(output_json_path, 'w') as f:
        json.dump(coco_dict, f, indent=2)
    
    # Write JSON points output if specified
    if args.output_json:
        output_json = OutputJson(points=json_results)
        with open(args.output_json, 'w') as f:
            json.dump(asdict(output_json), f, indent=2)
        logger.info(f"Successfully wrote JSON points to: {args.output_json}")
    
    logger.info(f"Successfully wrote COCO results to: {output_json_path}")
    logger.info(f"Total points found: {point_counter}")


def main():
    parser = argparse.ArgumentParser(description="A Python program to find laser points in images using color thresholds.")
    
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
    parser.add_argument("--start-threshold", type=int, default=255,
                       help="The start threshold for the color channel")
    parser.add_argument("--min-threshold", type=int, default=220,
                       help="The minimal threshold for the color channel")
    parser.add_argument("--min-point-area", type=float, default=5.0,
                       help="The minimal points size (area) to be detected")
    parser.add_argument("--num-lines", type=int, default=0,
                       help="The number of lines to search for, possible values: 0, 2, 3")
    
    # Color selection
    parser.add_argument("--red", action="store_true",
                       help="Optionally, search only for red laser points")
    parser.add_argument("--green", action="store_true", 
                       help="Optionally, search only for green laser points")
    parser.add_argument("--blue", action="store_true",
                       help="Optionally, search only for blue laser points")
    parser.add_argument("--use-lab", action="store_true",
                       help="Use LAB color space for the color channel")
    
    # Detection method
    parser.add_argument("--use-blobdetector", action="store_true",
                       help="Use SimpleBlobDetector instead of contour finding")
    
    # Image processing
    parser.add_argument("--gaussian-blur", type=int, default=0,
                       help="Kernel size for Gaussian blur (e.g., 3, 5, 7). 0 to disable.")
    parser.add_argument("--morph-open", type=int, default=0,
                       help="Kernel size for morphological opening (e.g., 3, 5). 0 to disable.")
    
    # Blob detector parameters
    parser.add_argument("--filter-by-circularity", action="store_true",
                       help="Enable filtering by circularity")
    parser.add_argument("--min-circularity", type=float, default=0.1,
                       help="Set the minimum circularity for blobs [0-1]")
    parser.add_argument("--filter-by-convexity", action="store_true",
                       help="Enable filtering by convexity")
    parser.add_argument("--min-convexity", type=float, default=0.87,
                       help="Set the minimum convexity for blobs [0-1]")
    parser.add_argument("--filter-by-inertia", action="store_true",
                       help="Enable filtering by inertia")
    parser.add_argument("--min-inertia-ratio", type=float, default=0.01,
                       help="Set the minimum inertia ratio for blobs [0-1]")
    parser.add_argument("--max-inertia-ratio", type=float, default=1.0,
                       help="Set the maximum inertia ratio for blobs [0-1]")
    
    # Execution options
    parser.add_argument("--single-threaded", action="store_true",
                       help="Execute in single threaded mode")
    parser.add_argument("--draw", action="store_true",
                       help="Draw circles on the image and save them for debugging")
    
    # Mode selection
    parser.add_argument("--mode", choices=["both", "lines-only", "detect-only", "detect-without-lines", "biigle_mode"],
                       default="both", help="Run mode")
    parser.add_argument("--subsample-size", type=int, default=100,
                       help="Number of images to subsample for line fitting in lines-only mode")
    parser.add_argument("--lines-file", default="fitted_lines.json",
                       help="File to save/load fitted lines")
    
    parser.add_argument("--laserdistance", type=float, default=50.0,help="Distance of the laser in meters, used for area estimation in biiigle mode")

    # Logging
    parser.add_argument("--verbose", "-v", action="store_true",
                       help="Enable verbose logging")
    
    args = parser.parse_args()
    
    # Set up logging
    log_level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=log_level,
        format='%(asctime)s - %(levelname)s - %(message)s'
    )
    logger = logging.getLogger(__name__)
    
    start_time = time.time()
    
    # Validate input arguments
    if not args.input and not args.input_json:
        parser.error("Either --input or --input-json must be provided")
    
    if args.input and args.input_json:
        parser.error("Cannot specify both --input and --input-json")
    
    # Validate mode-specific requirements
    if args.mode == "detect-only" and not Path(args.lines_file).exists():
        parser.error(f"In detect-only mode, the lines file '{args.lines_file}' must exist")
    
    # Validate blob detector usage
    if not args.use_blobdetector and (args.filter_by_circularity or args.filter_by_convexity or args.filter_by_inertia):
        logger.warning("Blob detector-specific filters were provided without the '--use-blobdetector' flag. These filters will be ignored.")
    
    # Validate Gaussian blur
    if args.gaussian_blur > 0 and args.gaussian_blur % 2 == 0:
        parser.error("Gaussian blur kernel size must be a positive odd number.")
    
    # Create output directory
    args.output.mkdir(parents=True, exist_ok=True)
    
    # Determine colors to search
    colors_to_search = []
    if args.red:
        colors_to_search.append(0)
    if args.green:
        colors_to_search.append(1)
    if args.blue:
        colors_to_search.append(2)
    
    if not colors_to_search:
        logger.info("No color specified, searching for all (Red, Green, Blue).")
        colors_to_search = [0, 1, 2]
    
    # Process input - either from file paths or JSON mapping
    img_paths = []
    img_id_map = None
    
    if args.input:
        # Traditional file/directory input
        for path in args.input:
            if path.is_dir():
                # Find all image files in directory
                for ext in ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff']:
                    img_paths.extend(path.glob(f"*.{ext}"))
                    img_paths.extend(path.glob(f"*.{ext.upper()}"))
            elif path.is_file():
                img_paths.append(path)
    elif args.input_json:
        # JSON input format
        with open(args.input_json, 'r') as f:
            input_json = json.load(f)
        
        img_id_map = {}
        for img_id, file_path in input_json.items():
            path = Path(file_path)
            if path.exists() and path.is_file():
                # Check if it's an image file
                if path.suffix.lower() in ['.jpg', '.jpeg', '.png', '.webp', '.tif', '.tiff']:
                    img_paths.append(path)
                    img_id_map[img_id] = path
            else:
                logger.warning(f"File not found or not accessible: {file_path}")
    
    logger.info(f"Found {len(img_paths)} images to process.")
    
    # Execute based on mode
    try:
        if args.mode == "both":
            run_lines_mode(args, img_paths, colors_to_search)
            run_detect_mode(args, img_paths, img_id_map, colors_to_search)
        elif args.mode == "lines-only":
            run_lines_mode(args, img_paths, colors_to_search)
        elif args.mode == "detect-only":
            run_detect_mode(args, img_paths, img_id_map, colors_to_search)
        elif args.mode == "detect-without-lines":
            run_detect_without_lines_mode(args, img_paths, img_id_map, colors_to_search)
        elif args.mode == "biigle_mode":
            run_biigle_mode(args, img_paths, img_id_map, colors_to_search)
        else:
            parser.error(f"Invalid mode: {args.mode}")
    except Exception as e:
        logger.error(f"Error during execution: {e}")
        sys.exit(1)
    
    execution_time = time.time() - start_time
    logger.info(f"Total execution time: {execution_time:.2f} seconds")


if __name__ == "__main__":
    main()
