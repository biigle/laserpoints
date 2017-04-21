import sys
import numpy as np
from scipy.misc import imread
import scipy.spatial.distance
import ast
import json

DISTANCE_THRESHOLD = 500
COLOR_THRESHOLD = 230

imgfile = sys.argv[1]
laserdistparam = sys.argv[2]

img = imread(imgfile)
if not len(img.shape):
    print json.dumps({
        "error": True,
        "message": "Could not load image. The image file might be corrupt.",
    })
    exit(1)
img[:, 0:int(img.shape[1] * 0.15), :] = 0
img[:, int(img.shape[1] - img.shape[1] * 0.15):, :] = 0

width = img.shape[0]
height = img.shape[1]
detection = ""
data = None
colorchannel = 0
if (len(sys.argv) == 4) and (sys.argv[3] != "[]"):
    # work with provided data points
    coords = ast.literal_eval(sys.argv[3])
    data = np.fliplr(np.array(coords))
    detection = "manual"
    COLOR_THRESHOLD = 0

laserpoints = coords
if laserpoints.shape[0] == 4:
    dists = scipy.spatial.distance.pdist(laserpoints)
    dists.sort()
    laserdist = float(laserdistparam) / 100.
    apx = np.mean(dists[0:4])**2

    if apx == 0:
        print json.dumps({
            "error": True,
            "message": "Computed pixel area is zero.",
            "method": detection
        })
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
