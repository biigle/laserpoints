import sys
import numpy as np
from scipy.misc import imread
import scipy.ndimage.morphology
import scipy.ndimage.measurements
import scipy.spatial.distance
import json

min_dist = 49.
detection = 'delphi'

inputFile = sys.argv[1]
imgfile = sys.argv[2]
laserdistparam = sys.argv[3]
data = np.load(inputFile)
maskImage = data['maskImage']
lps = data['lps']
manLaserpoints = data['manLaserpoints']
numLaserpoints = manLaserpoints.shape[1]

# apply
# load current image
img = imread(imgfile)
width, height, _ = img.shape
lpMap = np.zeros([img.shape[0], img.shape[1]], bool)


sel = np.where(maskImage)
sel2 = np.any(scipy.spatial.distance.cdist(img[maskImage], lps, 'cityblock') < min_dist, 1)
lpMap[sel[0][sel2], sel[1][sel2]] = 1

# closing of lp_map
lpMap = scipy.ndimage.morphology.binary_opening(lpMap, np.ones((3, 3)))

# find contours
lbls, nlabel = scipy.ndimage.measurements.label(lpMap)
# cts = scipy.ndimage.measurements.find_objects(lbls)
centers = np.array(scipy.ndimage.measurements.center_of_mass(lpMap, lbls, np.arange(1, nlabel + 1)))

# find best geometry
minDist = 10**6
lpWinner = []
for i in manLaserpoints:
    res = np.min(scipy.spatial.distance.cdist(centers, i), 1)
    asort = res.argsort()
    dist = np.sum(res[asort[0:numLaserpoints]])
    if dist < minDist:
        minDist = dist
        lpWinner = asort
laserpoints = centers[lpWinner]
if laserpoints.shape[0] == 3:
    a = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[1, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[1, 1]), 2))
    b = np.sqrt(np.power(float(laserpoints[1, 0]) - float(laserpoints[2, 0]), 2) + np.power(float(laserpoints[1, 1]) - float(laserpoints[2, 1]), 2))
    c = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[2, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[2, 1]), 2))
    laserdist = float(laserdistparam) / 100.
    s = 1.5 * laserdist
    are = np.sqrt(s * np.power(s - laserdist, 3))
    s = (a + b + c) / 2.
    apx = np.sqrt(s * (s - a) * (s - b) * (s - c))
    if apx == 0:
        print json.dumps({
            "error": True,
            "message": "Computed pixel area is zero.",
            "method": detection
        })
        exit(1)
    aqm = are * (float(width) * float(height)) / apx
elif laserpoints.shape[0] == 2:
    a = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[1, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[1, 1]), 2))
    flen = float(laserdistparam) / 100.
    aqm = (flen * width) / a * (flen * height) / a
else:
    # actually this should never happen
    print json.dumps({
        "error": True,
        "message": "Unsupported number of laserpoints.",
        "method": detection
    })
    exit(1)
pixelsize = width * height
if (aqm < 0.1):
    print json.dumps({
        "error": True,
        "message": "The estimated image area is too small (min is 0.1 sqm but was {} sqm).".format(round(aqm)),
        "method": detection,
    })
    exit(1)
elif (aqm > 50):
    print json.dumps({
        "error": True,
        "message": "The estimated image area is too large (max is 50 sqm but was {} sqm).".format(round(aqm)),
        "method": detection
    })
    exit(1)

# use fliplr to print coordinates as [x, y] tuples instead of [y, x]
print json.dumps({
    "error": False,
    "area": aqm,
    "px": pixelsize,
    "count": laserpoints.shape[0],
    "method": detection,
    "points": np.fliplr(laserpoints).tolist()
})
