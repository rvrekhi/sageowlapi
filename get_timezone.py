#!/usr/bin/env python3
import sys
from timezonefinder import TimezoneFinder

lat = float(sys.argv[1])
lon = float(sys.argv[2])
tf = TimezoneFinder()
tz = tf.timezone_at(lat=lat, lng=lon)

print(tz if tz else "UTC")
