import sys
import numpy as np
from scipy.misc import imread
import sklearn.cluster
import scipy.spatial.distance
import ast
import json

DISTANCE_THRESHOLD = 500
COLOR_THRESHOLD = 230

imgfile = sys.argv[1]
laserdistparam = sys.argv[2]

img = imread(imgfile)
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
else:
    detection = "heuristic"
    # extract red points
    thresholdr = COLOR_THRESHOLD
    datar = np.vstack((np.logical_and(img[:, :, 0] > thresholdr, img[:, :, 2] < 150)).nonzero()).T
    thresholdg = COLOR_THRESHOLD
    datag = np.vstack((np.logical_and(img[:, :, 1] > thresholdr, img[:, :, 2] < 150)).nonzero()).T
    if datar.size < datag.size and datag.size < 5000:
        data = datag
        colorchannel = 1
    else:
        data = datar
        colorchannel = 0
    if not data.size:
        # no red/green points found return error
        exit(1)

laserpoints = None
dists = None
if data.shape[0] > 2:
    km = sklearn.cluster.KMeans(n_clusters=3)
    km.fit(data)
    laserpoints = km.cluster_centers_.astype(np.int)
    dists = scipy.spatial.distance.pdist(laserpoints)
if dists is None or (dists and np.abs(dists[0] - dists[1]) > DISTANCE_THRESHOLD or np.abs(dists[1] - dists[2]) > DISTANCE_THRESHOLD or np.abs(dists[1] - dists[2]) > DISTANCE_THRESHOLD or np.any(img[[laserpoints[:, 0], laserpoints[:, 1], colorchannel]] < (COLOR_THRESHOLD * 0.9))):
    # three laserpoints does not work try two
    km = sklearn.cluster.KMeans(n_clusters=2)
    km.fit(data)
    laserpoints = km.cluster_centers_.astype(np.int)
    dists = scipy.spatial.distance.pdist(laserpoints)
    # check if clustering was correct could by accident approximate 3 laserpoints with 2 and if cluster points are actually green/red
    if np.sqrt(km.inertia_ / dists.shape[0]) > DISTANCE_THRESHOLD or np.any(img[[laserpoints[:, 0], laserpoints[:, 1], colorchannel]] < (COLOR_THRESHOLD * 0.9)):
        print laserpoints
        print img[[laserpoints[:, 0], laserpoints[:, 1], colorchannel]]
        exit(2)

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
