import sys
import numpy as np
from PIL import Image
import scipy.spatial.distance
import ast
import json

imgfile = sys.argv[1]
laserdistparam = sys.argv[2]

img = np.array(Image.open(imgfile))
if len(img.shape) == 0:
    print(json.dumps({
        "error": True,
        "message": "Could not load image. The image file might be corrupt.",
    }))
    exit(1)

width = img.shape[0]
height = img.shape[1]
laserpoints = np.array(ast.literal_eval(sys.argv[3]))

with open('/tmp/mfa.json', 'w') as f:
    json.dump(sys.argv, f)

detection = "manual"

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

# use fliplr to print coordinates as [x, y] tuples instead of [y, x]
print(json.dumps({
    "error": False,
    "area": aqm,
    "count": laserpoints.shape[0],
    "method": detection,
    "points": np.fliplr(laserpoints).tolist()
}))
