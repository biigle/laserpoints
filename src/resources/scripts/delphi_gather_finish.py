import sys, os
import numpy as np
import scipy.spatial.distance

output_path = sys.argv[1]
output = np.load(output_path)

lp_prototypes = output['lp_prototypes']
lp_neg_prototypes = output['lp_neg_prototypes']

distance = scipy.spatial.distance.cdist(lp_prototypes, lp_neg_prototypes, 'cityblock')
to_dismiss = np.any(distance < 49, 1)
to_dismiss_mask = np.logical_not(to_dismiss)
lp_prototypes = lp_prototypes[to_dismiss_mask]

np.savez_compressed(output_path,
    mask_image=output['mask_image'],
    lp_prototypes=lp_prototypes,
    all_laserpoints=output['all_laserpoints'])
# Rename the file because stupid numpy always appends a '.npz' to the file name.
os.rename(output_path + '.npz', output_path)
