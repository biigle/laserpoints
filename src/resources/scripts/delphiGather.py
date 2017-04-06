import sys
import numpy as np
from scipy.misc import imread
import ast

delta1 = 25

# get all laserpoints in this volume
manLaserpoints = ast.literal_eval(sys.argv[1])
manLaserpoints = np.fliplr(np.array(manLaserpoints))
manLaserpointFiles = ast.literal_eval(sys.argv[2])
output = sys.argv[3]

# preprocess

# for each lp
# load image
# create mask image with 50 px x 50 px positive around lps
tmpimg = imread(manLaserpointFiles[0])
lps = []
maskImage = np.zeros([tmpimg.shape[0], tmpimg.shape[1]], bool)
# lpConf = np.zeros([img.shape[0], img.shape[1]], np.uint8)
for idx, i in enumerate(manLaserpoints):
    lpimg = imread(manLaserpointFiles[idx])
    for j in i:
        maskImage[max(0, j[0] - delta1):min(j[0] + delta1, maskImage.shape[0]), max(0, j[1] - delta1):min(j[1] + delta1, maskImage.shape[1])] = 1
        # get color of lp
        # save color and x,y to array
        lps.append(lpimg[j[0], j[1]])
np.savez_compressed(output, maskImage=maskImage, lps=lps, manLaserpoints=manLaserpoints)
