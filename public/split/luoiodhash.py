#!/usr/bin/env python
#coding:utf-8

import dhash
import sys
from PIL import Image

image = Image.open(sys.argv[1])
row, col = dhash.dhash_row_col(image)
ret = dhash.format_hex(row, col)
print(ret)