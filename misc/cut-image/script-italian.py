from PIL import Image

# width: 72px;
# height: 123px;

X = 72
Y = 123
ROWS = 4
COLS = 10
NEW_COLS = 10

src = Image.open("italian-cards.jpg")
dst = Image.new('RGB', (X * NEW_COLS, Y * ROWS), (255, 255, 255))

# Remove A, 3
for x in range(0, X * COLS + 1, X):
	for y in range(0, Y * ROWS + 1, Y):
		if x == 0:
			# Skip A
			continue
		if x == X:
			# Copy 2 shifted by 1
			src_box = (x, y, x + X, y + Y)
			dst_box = (x - 1 * X, y, x + X - 1 * X, y + Y)
		elif x == 2 * X:
			# Skip 3
			continue
		else:
			# Copy everything else shifted by 2
			src_box = (x, y, x + X, y + Y)
			dst_box = (x - 2 * X, y, x + X - 2 * X, y + Y)

		region = src.crop(src_box)
		dst.paste(region, dst_box)

# Add 3 and A
for y in range(0, Y * ROWS, Y):
	src_box = (2 * X, y, 3 * X, y + Y)
	dst_box = (X * NEW_COLS - 2 * X, y, X * NEW_COLS - 1 * X, y + Y)
	region = src.crop(src_box)
	dst.paste(region, dst_box)

for y in range(0, Y * ROWS, Y):
	src_box = (0, y, X, y + Y)
	dst_box = (X * NEW_COLS - 1 * X, y, X * NEW_COLS, y + Y)
	region = src.crop(src_box)
	dst.paste(region, dst_box)

# Swap first and last row of cards, due to suit order in french cards
first_row_region = (0, 0, X * NEW_COLS, Y)
first_row = dst.crop(first_row_region)

last_row_region = (0, Y * ROWS - Y, X * NEW_COLS, Y * ROWS)
last_row = dst.crop(last_row_region)

dst.paste(first_row, last_row_region)
dst.paste(last_row, first_row_region)

dst.save("italian-output.jpg")
