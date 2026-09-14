#!/bin/bash -e
# Standard pi-gen stage boilerplate: carry the previous stage's filesystem
# forward as this stage's starting point.
if [ ! -d "${ROOTFS_DIR}" ]; then
	copy_previous
fi
