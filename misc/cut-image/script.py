from PIL import Image

# width: 72px;
# height: 96px;

X = 72
Y = 96
ROWS = 4
COLS = 13
NEW_COLS = 10

src = Image.open("cards.jpg")
dst = Image.new('RGB', (X * NEW_COLS, Y * ROWS), (255, 255, 255))

for x in range(0, X * COLS + 1, X):
	for y in range(0, Y * ROWS - Y + 1, Y):
		if x < X * 6:
			box = (x, y, x + X, y + Y)

			region = src.crop(box)
			dst.paste(region, box)
		elif x == X * 6 or x == X * 7 or x == X * 8:
			continue
		else:
			src_box = (x, y, x + X, y + Y)
			dst_box = (x - 3 * X, y, x + X - 3 * X, y + Y)

			region = src.crop(src_box)
			dst.paste(region, dst_box)

dst.save("output.jpg")
