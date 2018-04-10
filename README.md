# Image

Simple GD image manipulation library

## Usage
```php
use Image\Image;

$image = Image::createFromFile('lena.jpg');
$thumb = $image->thumbnail(100, 100);
$thumb->save('lena-thumb.jpg');
```