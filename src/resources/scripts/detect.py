import sys
import numpy as np
from scipy.misc import imread
import sklearn.cluster
import scipy.spatial.distance
import ast
import json

DISTANCE_THRESHOLD = 500

imgfile = sys.argv[1]
laserdistparam = sys.argv[2]

img = imread(imgfile)
width = img.shape[0]
height = img.shape[1]
detection = ""
data = None
if (len(sys.argv) == 4) and (sys.argv[3] != "[]"):
    # work with provided data points
    coords = ast.literal_eval(sys.argv[3])
    data = np.fliplr(np.array(coords))
    detection = "manual"
else:
    detection = "heuristic"
    # extract red points
    thresholdr = 240
    datar = np.vstack((img[:, :, 1] > thresholdr).nonzero()).T
    thresholdg = 240
    datag = np.vstack((img[:, :, 0] > thresholdg).nonzero()).T
    if datar.size < datag.size and datag.size < 5000:
        data = datag
    else:
        data = datar
    if not data.size:
        # no red/green points found return error
        exit(1)

km = sklearn.cluster.KMeans(n_clusters=3)
km.fit(data)
laserpoints = km.cluster_centers_.astype(np.int)
dists = scipy.spatial.distance.pdist(laserpoints)
if np.abs(dists[0] - dists[1]) > DISTANCE_THRESHOLD or np.abs(dists[1] - dists[2]) > DISTANCE_THRESHOLD or np.abs(dists[1] - dists[2]) > DISTANCE_THRESHOLD:
    # three laserpoints does not work try two
    km = sklearn.cluster.KMeans(n_clusters=2)
    dists = km.fit_transform(data)
    # check if clustering was correct could by accident approximate 3 laserpoints with 2
    if np.sqrt(km.inertia_ / dists.shape[0]) > DISTANCE_THRESHOLD:
        print laserpoints
        exit(2)
    laserpoints = km.cluster_centers_.astype(np.int)
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
        exit(3)
    aqm = are * (float(width) * float(height)) / apx
elif laserpoints.shape[0] == 2:
    a = np.sqrt(np.power(float(laserpoints[0, 0]) - float(laserpoints[1, 0]), 2) + np.power(float(laserpoints[0, 1]) - float(laserpoints[1, 1]), 2))
    flen = float(laserdistparam) / 100.
    aqm = (flen * width) / a * (flen * height) / a
else:
    # actually this should never happen
    exit(4)
pixelsize = width * height
if (aqm < 0.1) or (aqm > 50):
    exit(5)
# use fliplr to print coordinates as [x, y] tuples instead of [y, x]
print json.dumps({"area": aqm, "px": pixelsize, "count": laserpoints.shape[0], "method": detection, "points": np.fliplr(laserpoints).tolist()})
