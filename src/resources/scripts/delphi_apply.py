import sys
import json
import numpy as np
from PIL import Image
import scipy.ndimage.morphology
import scipy.ndimage.measurements
import scipy.spatial.distance

min_dist = 49.
detection = 'delphi'

inputFile = sys.argv[1]
imgfile = sys.argv[2]
laserdistparam = sys.argv[3]
data = np.load(inputFile)
mask_image = data['mask_image']
lp_prototypes = data['lp_prototypes']
all_laserpoints = data['all_laserpoints']
numLaserpoints = all_laserpoints.shape[1]

try:
    img = np.array(Image.open(imgfile))
except IOError:
    # It may be another error than "cannot identify image file" but we don't print the
    # error message to not expose any internal file paths.
    print(json.dumps({
        "error": True,
        "message": "Could not load image. Cannot identify image file.",
    }))
    exit(1)

if len(img.shape) == 0:
    print(json.dumps({
        "error": True,
        "message": "Could not load image. The image file might be corrupt.",
    }))
    exit(1)
width, height, _ = img.shape
lpMap = np.zeros([img.shape[0], img.shape[1]], bool)


sel = np.where(mask_image)
try:
    sel2 = np.zeros(img[mask_image].shape[0], bool)
except IndexError as e:
    print(json.dumps({
        "error": True,
        "message": "The image has a different size than the reference image.",
    }))
    exit(1)

for idx, i in enumerate(img[mask_image]):
    sel2[idx] = np.any(scipy.spatial.distance.cdist(np.atleast_2d(i), lp_prototypes, 'cityblock') < min_dist)
lpMap[sel[0][sel2], sel[1][sel2]] = 1

# opening of lp_map
# lpMap = scipy.ndimage.morphology.binary_opening(lpMap, np.ones((3, 3)))

# find contours
lbls, nlabel = scipy.ndimage.measurements.label(lpMap)
centers = np.array(scipy.ndimage.measurements.center_of_mass(lpMap, lbls, np.arange(1, nlabel + 1)))

if centers.shape == (0,):
    print(json.dumps({
        "error": True,
        "message": "No laserpoints could be detected.",
        "method": detection,
    }))
    exit(1)

# find best geometry
minDist = 10**6
lpWinner = []
for i in all_laserpoints:
    res = np.min(scipy.spatial.distance.cdist(centers, i), 1)
    asort = res.argsort()
    dist = np.sum(res[asort[0:numLaserpoints]])
    if dist < minDist:
        minDist = dist
        lpWinner = asort
laserpoints = centers[lpWinner[0:numLaserpoints]]


if laserpoints.shape[0] == 4:
    dists = scipy.spatial.distance.pdist(laserpoints)
    dists.sort()
    laserdist = float(laserdistparam) / 100.
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
    laserdist = float(laserdistparam) / 100.
    s = 1.5 * laserdist
    are = np.sqrt(s * np.power(s - laserdist, 3))
    s = (a + b + c) / 2.
    apx = np.sqrt(s * (s - a) * (s - b) * (s - c))
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
    flen = float(laserdistparam) / 100.
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

# use fliplr to print coordinates as [x, y] tuples instead of [y, x]
print(json.dumps({
    "error": False,
    "area": aqm,
    "count": laserpoints.shape[0],
    "method": detection,
    "points": np.fliplr(laserpoints).tolist()
}))
