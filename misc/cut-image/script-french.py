from PIL import Image

# width: 72px;
# height: 96px;

X = 72
Y = 96
ROWS = 4
COLS = 13
NEW_COLS = 10

src = Image.open("french-cards.jpg")
dst = Image.new('RGB', (X * NEW_COLS, Y * ROWS), (255, 255, 255))

# Remove 3, 8, 9, 10
for x in range(0, X * COLS - X, X):
	for y in range(0, Y * ROWS, Y):
		if x < X:
			# Copy until 3
			src_box = dst_box = (x, y, x + X, y + Y)
		elif x == X:
			# Skip 3
			continue
		elif x < X * 6:
			# Copy until 7 (included) shifted by 1
			src_box = (x, y, x + X, y + Y)
			dst_box = (x - 1 * X, y, x + X - 1 * X, y + Y)
		elif x == X * 6 or x == X * 7 or x == X * 8:
			# Skip 8, 9, 10
			continue
		else:
			# Copy J, Q, K shifted by 4
			src_box = (x, y, x + X, y + Y)
			dst_box = (x - 4 * X, y, x + X - 4 * X, y + Y)

		region = src.crop(src_box)
		dst.paste(region, dst_box)

# Add 3 and A
for y in range(0, Y * ROWS, Y):
	src_box = (X, y, 2 * X, y + Y)
	dst_box = (X * NEW_COLS - 2 * X, y, X * NEW_COLS - 1 * X, y + Y)
	region = src.crop(src_box)
	dst.paste(region, dst_box)

for y in range(0, Y * ROWS, Y):
	src_box = (X * COLS - 1 * X, y, X * COLS, y + Y)
	dst_box = (X * NEW_COLS - 1 * X, y, X * NEW_COLS, y + Y)
	region = src.crop(src_box)
	dst.paste(region, dst_box)

dst.save("french-output.jpg")
