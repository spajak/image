<?php

namespace Spajak;

use RuntimeException;
use InvalidArgumentException;

class Image
{
    const JPEG = 'jpeg';
    const PNG  = 'png';

    const THUMBNAIL_OUTER = 0;
    const THUMBNAIL_INNER = 1;

    protected $resource;


    /**
     * Constructs a new Image instance using the result of
     * imagecreatetruecolor()
     *
     * @param resource $resource GD image resource
     */
    public function __construct($resource)
    {
        $this->resource = $resource;
    }


    /**
     * Makes sure the current image resource is destroyed
     */
    public function __destruct()
    {
        if (is_resource($this->resource)) {
            imagedestroy($this->resource);
        }
    }


    /**
     * Image string representation
     *
     * @return string Image dimensions
     */
    public function __toString()
    {
        return $this->getWidth().'x'.$this->getHeight();
    }


    /**
     * Internal. Create new canvas
     *
     * @param integer $width  Image width
     * @param integer $height Image height
     *
     * @throws RuntimeException
     * @return resource GD image resource
     */
    protected function createCanvas($width, $height)
    {
        if (false === $resource = imagecreatetruecolor($width, $height)) {
            throw new RuntimeException('Creating new image failed');
        }

        if (false === imagealphablending($resource, false) or false === imagesavealpha($resource, true)) {
            throw new RuntimeException('Failed to set image alphablending and savealpha');
        }

        return $resource;
    }


    /**
     * Copy of the image
     *
     * @throws RuntimeException
     * @return Image New Image instance
     */
    public function copy()
    {
        $resource = $this->createCanvas($this->getWidth(), $this->getHeight());

        $result = imagecopy($resource, $this->resource,
            0, 0, // dst(x,y)
            0, 0, // src(x,y)
            $this->getWidth(), $this->getHeight() // src(width,height)
        );

        if ($result === false) {
            throw new RuntimeException('Failed to copy image');
        }

        return new Image($resource);
    }


    /**
     * Create resized copy of the image
     *
     * @param integer $width  Width of the new image
     * @param integer $height Height of the new image
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return Image Resized copy of the image
     */
    public function resize($width, $height)
    {
        $width = (int) $width;
        $height = (int) $height;

        if ($width < 1 or $height < 1) {
            throw new InvalidArgumentException(sprintf('Image dimension cannot be less than 1, %dx%d given.', $width, $height));
        }

        $resource = $this->createCanvas($width, $height);

        $result = imagecopyresampled($resource, $this->resource,
            0, 0, // dst(x,y)
            0, 0, // src(x,y)
            $width, $height, // dst(width,height)
            $this->getWidth(), $this->getHeight() // src(width,height)
        );

        if ($result === false) {
            throw new RuntimeException('Failed to resize image');
        }

        return new Image($resource);
    }


    /**
     * Crop the image
     *
     * @param integer $width  Width of the cropped image
     * @param integer $height Height of the cropped image
     * @param integer $x      Crop start point x coordinate
     * @param integer $y      Crop start point y coordinate
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return Image Cropped copy of the image
     */
    public function crop($width, $height, $x = 0, $y = 0)
    {
        $width = (int) $width;
        $height = (int) $height;

        if ($width < 1 or $height < 1) {
            throw new InvalidArgumentException(sprintf('Image dimension cannot be less than 1, %dx%d given.', $width, $height));
        }

        $x = (int) $x;
        $y = (int) $y;

        if ($x < 0 or $y < 0) {
            throw new InvalidArgumentException(sprintf('Image coordinate cannot be negative, (%d,%d) given', $x, $y));
        }

        if ($width + $x > $this->getWidth() or $height + $y > $this->getHeight()) {
            throw new RuntimeException('Cannot crop the image outside it\'s boundary.');
        }

        $resource = $this->createCanvas($width, $height);

        $result = imagecopy($resource, $this->resource,
            0, 0, // dst(x,y)
            $x, $y, // src(x,y)
            $width, $height  // src(width,height)
        );

        if ($result === false) {
            throw new RuntimeException('Failed to crop image');
        }

        return new Image($resource);
    }


    /**
     * Paste an image into this image
     *
     * @param Image $image An image to paste
     * @param integer $x   Start point x coordinate
     * @param integer $y   Start point y coordinate
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return Image
     */
    public function paste(Image $image, $x = 0, $y = 0)
    {
        $x = (int) $x;
        $y = (int) $y;

        if ($x < 0 or $y < 0) {
            throw new InvalidArgumentException(sprintf('Image coordinate cannot be negative, (%d,%d) given', $x, $y));
        }

        if ($image->getWidth() + $x > $this->getWidth() or $image->getHeight() + $y > $this->getHeight()) {
            throw new RuntimeException(sprintf('Paste operation cannot be done because source image goes outside image boundary.', $x, $y));
        }

        imagealphablending($this->resource, true);
        imagealphablending($image->resource, true);

        $result = imagecopy($this->resource, $image->resource,
            $x, $y, // dst(x,y)
            0, 0, // src(x,y)
            $image->getWidth(), $image->getHeight() // src(width,height)
        );

        if ($result === false) {
            throw new RuntimeException('Failed to paste into image');
        }

        imagealphablending($this->resource, false);
        imagealphablending($image->resource, false);

        return $this;
    }


    /**
     * Generate thumbnail image
     *
     * @param integer $maxWidth  Width of the thumb image
     * @param integer $maxHeight Height of the thumb image
     * @param integer $mode      THUMBNAIL_OUTER or THUMBNAIL_INNER constants
     *
     * @throws InvalidArgumentException
     * @return Image Thumbnail image
     */
    public function thumbnail($maxWidth, $maxHeight, $mode = self::THUMBNAIL_OUTER)
    {
        $maxWidth = (int) $maxWidth;
        $maxHeight = (int) $maxHeight;
        $width = $this->getWidth();
        $height = $this->getHeight();

        if ($mode === self::THUMBNAIL_OUTER) {
            if ($maxWidth > 0 and $maxWidth < $width) { // Apply width constraint
                $height = (int) round($height * $maxWidth / $width);
                $width = $maxWidth;
            }

            if ($maxHeight > 0 and $maxHeight < $height) { // Apply height constraint
                $width = (int) round($width * $maxHeight / $height);
                $height = $maxHeight;
            }

            return $this->resize($width, $height);
        }

        if ($mode === self::THUMBNAIL_INNER) {
            $x = $y = 0;

            if ($maxWidth > 0 and $maxWidth < $width) {
                $x = (int) round(($width - $maxWidth)/2);
                $width = $maxWidth;
            }

            if ($maxHeight > 0 and $maxHeight < $height) {
                $y = (int) round(($height - $maxHeight)/2);
                $height = $maxHeight;
            }

            return $this->crop($width, $height, $x, $y);
        }

        throw new InvalidArgumentException('Specified thumbnail mode is not supported');
    }


    /**
     * Sharpen image
     *
     * @param float $factor Value from 0 to 1. Higher values give sharper image
     *
     * @throws RuntimeException
     * @return Image
     */
    public function sharpen($factor)
    {
        $c = (int) round((1-pow(min(1, max(0, (float) $factor)), 1/2)) * 11 + 5);

        $matrix = [
            [ 0, -1,  0],
            [-1, $c, -1],
            [ 0, -1,  0],
        ];

        // calculate the sharpen divisor
        $divisor = array_sum(array_map('array_sum', $matrix));

        $offset = 0;

        // apply the matrix
        if (false === imageconvolution($this->resource, $matrix, $divisor, $offset)) {
            throw new RuntimeException('Failed to apply image convolution');
        }

        return $this;
    }


    /**
     * Image resource
     *
     * @return resource GD image resource
     */
    public function getResource()
    {
        return $this->resource;
    }


    /**
     * Image width
     *
     * @return integer
     */
    public function getWidth()
    {
        return imagesx($this->resource);
    }


    /**
     * Image height
     *
     * @return integer
     */
    public function getHeight()
    {
        return imagesy($this->resource);
    }


    /**
     * Return color at x,y coordinate
     *
     * @param int $x X coordinate
     * @param int $y Y coordinate
     *
     * @throws InvalidArgumentException
     * @return array Color (r,g,b,a)
     */
    public function getColorAt($x = 0, $y = 0)
    {
        $x = (int) $x;
        $y = (int) $y;

        if ($x < 0 or $y < 0) {
            throw new InvalidArgumentException(sprintf('Image coordinate cannot be negative, (%d,%d) given', $x, $y));
        }

        $info = imagecolorsforindex($this->resource, imagecolorat($this->resource, $x, $y));

        return [$info['red'], $info['green'], $info['blue'], (int) round($info['alpha'] / 127 * 100)];
    }


    /**
     * Get image data as a string
     *
     * @param string  $format  Image format, jpeg or png
     * @param integer $quality Percentage
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return string
     */
    public function getData($format, $quality = 85)
    {
        $format = strtolower($format);

        if (!in_array($format, [self::JPEG, self::PNG])) {
            throw new InvalidArgumentException(sprintf('Image format "%s" is not supported', $format));
        }

        if ($format === self::PNG) {
            $quality = 9;
        }

        $callback = 'image'.$format;

        ob_start();
        if (false == call_user_func($callback, $this->resource, null, $quality)) {
            @ob_end_clean();
            throw new RuntimeException(sprintf('Unable to call "%s"', $callback));
        }

        return ob_get_clean();
    }


    /**
     * Save image to file
     *
     * @param string  $path    File path
     * @param string  $format  Image format, jpeg or png
     * @param integer $quality Percentage
     *
     * @throws RuntimeException
     * @return string  Image data
     */
    public function save($path, $format = 'jpeg', $quality = 85)
    {
        $dir = dirname($path);

        if (!is_dir($dir) and false === @mkdir($dir, 0777, true)) {
            throw new RuntimeException(sprintf('Unable to create the directory "%s"', $dir));
        }

        $data = $this->getData($format, $quality);

        if (false === file_put_contents($path, $data, LOCK_EX)) {
            throw new RuntimeException(sprintf('Unable to save image to "%s"', $path));
        }

        return $data;
    }


    /**
     * Create new image
     *
     * @param integer      $width  Width of the image
     * @param integer      $height Height of the image
     * @param array|string $color
     * @param int          $alpha
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return Image
     */
    public static function create($width, $height, $color = null, $alpha = null)
    {
        $width = (int) $width;
        $height = (int) $height;

        if ($width < 1 or $height < 1) {
            throw new InvalidArgumentException(sprintf('Image dimension cannot be less than 1, %dx%d given.', $width, $height));
        }

        if (false === $resource = imagecreatetruecolor($width, $height)) {
            throw new RuntimeException('Creating new image failed');
        }

        if (false === imagealphablending($resource, false) or false === imagesavealpha($resource, true)) {
            throw new RuntimeException('Failed to set image alphablending and savealpha');
        }

        if (null === $color) {
            $color = [255, 255, 255];
        }

        $color = self::parseColor($color, $alpha);

        if (false === $bgColor = imagecolorallocatealpha($resource, $color[0], $color[1], $color[2], $color[3])) {
            throw new RuntimeException('Failed to allocate image color');
        }

        if (false === imagefill($resource, 0, 0, $bgColor)) {
            throw new RuntimeException('Could not set background color fill');
        }

        return new Image($resource);
    }


    /**
     * Create Image from the given string data
     *
     * @param string $data Image data
     *
     * @throws RuntimeException
     * @return Image
     */
    public static function createFromString($data)
    {
        if (false === $resource = imagecreatefromstring($data)) {
            throw new RuntimeException('Image data is not in a recognised format or is corrupted');
        }

        return new Image($resource);
    }


    /**
     * Create Image from the given file path
     *
     * @param string $path Image file path
     *
     * @throws RuntimeException
     * @return Image
     */
    public static function createFromFile($path)
    {
        if (false === $data = @file_get_contents($path)) {
            throw new RuntimeException(sprintf('Image file cannot be read "%s"', $path));
        }

        return self::createFromString($data);
    }


    /**
     * Parse color
     *
     * @param string|array $color
     * @param integer      $alpha
     *
     * @throws InvalidArgumentException
     * @return array Color array (r,g,b,a)
     */
    public static function parseColor($color, $alpha = null)
    {
        if (is_string($color)) {
            $color = ltrim($color, '#');

            if (strlen($color) === 3) {
                $color = $color[0].$color[0].$color[1].$color[1].$color[2].$color[2];
            } else if (strlen($color) !== 6) {
                throw new InvalidArgumentException(sprintf('Color must be a hex value in regular (6 characters) or short (3 characters) notation, "%s" given', $color));
            }

            $color = array_map('hexdec', str_split($color, 2));
        } else if (is_array($color)) {
            if (count($color) < 3) {
                throw new InvalidArgumentException('Color has to be an array of at least 3 integers (r,g,b): 0-255');
            }

            $color[0] = max(0, min(255, (int) $color[0]));
            $color[1] = max(0, min(255, (int) $color[1]));
            $color[2] = max(0, min(255, (int) $color[2]));
        } else {
            throw new InvalidArgumentException(sprintf('Color has to be string or array, %s given', gettype($color)));
        }

        if ($alpha === null) {
            $alpha = isset($color[3]) ? $color[3] : 0;
        }

        $color[3] = max(0, min(127, (int) round($alpha * 127 / 100)));

        return $color;
    }
}
