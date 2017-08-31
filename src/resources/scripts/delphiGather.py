import sys
import os
import numpy as np
from scipy.misc import imread
import scipy.spatial.distance
import json

delta1 = 25
delta2 = 1
min_dist = 49

f = open(sys.argv[1], 'r')
js = json.load(f)
f.close()

'''
Expected input file format:
{
    filePrefix: "/path/to/files",
    manLaserpoints: [
        [[10,10],[20,20]],
        [[40,40],[50,50]]
    ],
    manLaserpointFiles: [
        "path/to/file1.jpg",
        "path/to/file2.jpg"
    ],
    tmpFile: "/tmp/filepath"
}
'''

filePrefix = js['filePrefix']
manLaserpoints = js['manLaserpoints']
manLaserpoints = np.array(manLaserpoints, int)[:, :, ::-1]
manLaserpointFiles = [filePrefix + '/' + file for file in js['manLaserpointFiles']]
output = js['tmpFile']

# preprocess

# for each lp
# load image
# create mask image with 50 px x 50 px positive around lps
tmpimg = imread(manLaserpointFiles[0])

lps = np.zeros((manLaserpoints.shape[0] * manLaserpoints.shape[1], 3))
lpnegativ = np.zeros((manLaserpoints.shape[0] * manLaserpoints.shape[1] * 4, 3))
lps_index = 0

maskImage = np.zeros([tmpimg.shape[0], tmpimg.shape[1]], bool)
for idx, i in enumerate(manLaserpoints):
    lpimg = imread(manLaserpointFiles[idx])
    for idx2, j in enumerate(i):
        maskImage[max(0, j[0] - delta1):min(j[0] + delta1, maskImage.shape[0]), max(0, j[1] - delta1):min(j[1] + delta1, maskImage.shape[1])] = 1
        # save color of lp to array
        lps[lps_index] = lpimg[j[0], j[1]]
        try:
            lpnegativ[lps_index * 4] = lpimg[j[0] - delta1 / 2, j[1] - delta1 / 2]
        except IndexError:
            pass
        try:
            lpnegativ[lps_index * 4 + 1] = lpimg[j[0] + delta1 / 2, j[1] + delta1 / 2]
        except IndexError:
            pass
        try:
            lpnegativ[lps_index * 4 + 2] = lpimg[j[0] - delta1 / 2, j[1] + delta1 / 2]
        except IndexError:
            pass
        try:
            lpnegativ[lps_index * 4 + 3] = lpimg[j[0] + delta1 / 2, j[1] - delta1 / 2]
        except IndexError:
            pass
        lps_index += 1

lps = lps[np.logical_not(np.any(scipy.spatial.distance.cdist(lps, lpnegativ, 'cityblock') < 49, 1))]

np.savez_compressed(output, maskImage=maskImage, lps=lps, manLaserpoints=manLaserpoints)
# rename the file because stupid numpy always appends a '.npz' to the file name
os.rename(output + '.npz', output)
