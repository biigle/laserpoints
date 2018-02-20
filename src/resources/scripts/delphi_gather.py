import sys, os, json
import numpy as np
from scipy.misc import imread

'''
Expected input arguments:
delphi_gather.py <image_path> <laserpoints> <output_path>

laserpoints are JSON formatted like: [[10,10],[20,20]].
'''

delta = 25
min_dist = 49

image_path = sys.argv[1]
laserpoints = json.loads(sys.argv[2])
output_path = sys.argv[3]

image = imread(image_path)
# If the shape is empty the image wasn't read correctly. We just skip this file.
# See: https://github.com/BiodataMiningGroup/biigle-laserpoints/issues/24
if len(image.shape) is 0: sys.exit(0)
height, width, _ = image.shape

if os.path.exists(output_path):
    output = np.load(output_path)
    mask_image = output['mask_image']
    lp_prototypes = output['lp_prototypes'].tolist()
    lp_neg_prototypes = output['lp_neg_prototypes'].tolist()
    all_laserpoints = output['all_laserpoints'].tolist()
else:
    mask_image = np.zeros([height, width], bool)
    lp_prototypes = []
    lp_neg_prototypes = []
    all_laserpoints = []

all_laserpoints.append(laserpoints)

for i, point in enumerate(laserpoints):
    mask_image[
        max(0, point[1] - delta):min(point[1] + delta, height),
        max(0, point[0] - delta):min(point[0] + delta, width)
    ] = 1

    lp_prototypes.append(image[point[1], point[0]])

    try:
        lp_neg_prototypes.append(image[point[1] - delta / 2, point[0] - delta / 2])
    except IndexError:
        pass

    try:
        lp_neg_prototypes.append(image[point[1] - delta / 2, point[0] + delta / 2])
    except IndexError:
        pass

    try:
        lp_neg_prototypes.append(image[point[1] + delta / 2, point[0] - delta / 2])
    except IndexError:
        pass

    try:
        lp_neg_prototypes.append(image[point[1] + delta / 2, point[0] + delta / 2])
    except IndexError:
        pass

np.savez_compressed(output_path,
    mask_image=mask_image,
    lp_prototypes=lp_prototypes,
    lp_neg_prototypes=lp_neg_prototypes,
    all_laserpoints=all_laserpoints)
# Rename the file because stupid numpy always appends a '.npz' to the file name.
os.rename(output_path + '.npz', output_path)
