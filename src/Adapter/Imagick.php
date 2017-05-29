<?php
namespace Kof\Thumb\Adapter;

use Kof\Thumb\Exception\InvalidArgumentException;
use Kof\Thumb\Exception\RuntimeException;

class Imagick extends AbstractAdapter
{
    /**
     * The prior image (before manipulation)
     *
     * @var \Imagick
     */
    protected $imagick;

    /**
     * Imagick constructor.
     * @param string $fileName
     * @param array|null $options
     * @param bool $isDataStream
     */
    public function __construct($fileName, array $options = null, $isDataStream = false)
    {
        parent::__construct($fileName, $options, $isDataStream);

        $this->imagick = new \Imagick();

        if ($isDataStream) {
            $this->imagick->readImageBlob($this->fileName);
        } else {
            $this->imagick->readImage($this->fileName);
        }
    }

    /**
     * @inheritdoc
     */
    public function resize($maxWidth = 0, $maxHeight = 0)
    {
        // make sure our arguments are valid
        if (!is_numeric($maxWidth)) {
            throw new InvalidArgumentException('$maxWidth must be numeric');
        }

        if (!is_numeric($maxHeight)) {
            throw new InvalidArgumentException('$maxHeight must be numeric');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if (!$this->getOption('resizeUp')) {
            $this->maxHeight = (intval($maxHeight) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $maxHeight;
            $this->maxWidth = (intval($maxWidth) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $maxWidth;
        } else {
            $this->maxHeight = intval($maxHeight);
            $this->maxWidth = intval($maxWidth);
        }

        // get the new dimensions...
        $this->calcImageSize($this->currentDimensions['width'], $this->currentDimensions['height']);

        $this->imagick->setIteratorIndex(0);
        while (true) {
            $this->imagick->scaleImage($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
            if (!$this->imagick->nextImage()) {
                break;
            }
        }
        $this->currentDimensions['width'] = $this->newDimensions['newWidth'];
        $this->currentDimensions['height'] = $this->newDimensions['newHeight'];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function copymerge(AdapterInterface $mergeAdapter, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct = 100)
    {
        $mask = new \Imagick();
        $mask->readImageBlob($mergeAdapter->__toString());

        $this->imagick->setIteratorIndex(0);
        while (true) {
            $this->imagick->setImageMatte(1);
            $this->imagick->compositeImage(
                $mask,
                constant("Imagick::COMPOSITE_DSTIN"),
                $dst_x,
                $dst_y,
                $pct == 100
                    ? constant("Imagick::CHANNEL_TRUEALPHA")
                    : constant("Imagick::CHANNEL_ALL")
            );

            if (!$this->imagick->nextImage()) {
                break;
            }
        }

        $this->crop($src_x, $src_y, $src_w, $src_h);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function ttftext($size, $angle, $x, $y, array $color, $fontfile, $text)
    {
        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel("rgb({$color[0]}, {$color[1]}, {$color[2]})"));
        $draw->setFont($fontfile);
        $draw->setFontSize($size);
        $draw->setTextAntialias(true);

        if ($x < 0) {
            if ($y < 0) {
                $x = $x * -1;
                $y = $y * -1;
                $gravity = constant("Imagick::GRAVITY_SOUTHEAST");
            } else {
                $x = $x * -1;
                $gravity = constant("Imagick::GRAVITY_NORTHEAST");
            }
        } else {
            if ($y < 0) {
                $x = 0;
                $y = $y * -1;
                $gravity = constant("Imagick::GRAVITY_SOUTHWEST");
            } else {
                $x = 0;
                $gravity = constant("Imagick::GRAVITY_NORTHWEST");
            }
        }

        $draw->setGravity($gravity);
        $this->imagick->setIteratorIndex(0);
        while (true) {
            $this->imagick->annotateImage($draw, $x, $y, $angle, $text);
            if (!$this->imagick->nextImage()) {
                break;
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function adaptiveResize($width, $height)
    {
        // make sure our arguments are valid
        if (!is_numeric($width) || $width == 0) {
            throw new InvalidArgumentException('$width must be numeric and greater than zero');
        }

        if (!is_numeric($height) || $height == 0) {
            throw new InvalidArgumentException('$height must be numeric and greater than zero');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->options['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        $this->calcImageSizeStrict($this->currentDimensions['width'], $this->currentDimensions['height']);

        // resize the image to be close to our desired dimensions
        $this->resize($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);

        // reset the max dimensions...
        if ($this->options['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->maxWidth, $this->maxHeight);
        } else {
            $this->workingImage = imagecreate($this->maxWidth, $this->maxHeight);
        }

        $cropWidth = $this->maxWidth;
        $cropHeight = $this->maxHeight;
        $cropX = 0;
        $cropY = 0;

        // now, figure out how to crop the rest of the image...
        if ($this->currentDimensions['width'] > $this->maxWidth) {
            $cropX = intval(($this->currentDimensions['width'] - $this->maxWidth) / 2);
        } elseif ($this->currentDimensions['height'] > $this->maxHeight) {
            $cropY = intval(($this->currentDimensions['height'] - $this->maxHeight) / 2);
        }

        $this->crop($cropX, $cropY, $cropWidth, $cropHeight);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function resizePercent($percent = 0)
    {
        if (!is_numeric($percent)) {
            throw new InvalidArgumentException ('$percent must be numeric');
        }

        $this->percent = intval($percent);

        $this->calcImageSizePercent($this->currentDimensions['width'], $this->currentDimensions['height']);

        $this->imagick->setIteratorIndex(0);
        while (true) {
            $this->imagick->scaleImage($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
            if (!$this->imagick->nextImage()) {
                break;
            }
        }
        $this->currentDimensions['width'] = $this->newDimensions['newWidth'];
        $this->currentDimensions['height'] = $this->newDimensions['newHeight'];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function cropFromCenter($cropWidth, $cropHeight = null)
    {
        if (!is_numeric($cropWidth)) {
            throw new InvalidArgumentException('$cropWidth must be numeric');
        }

        if ($cropHeight !== null && !is_numeric($cropHeight)) {
            throw new InvalidArgumentException('$cropHeight must be numeric');
        }

        if ($cropHeight === null) {
            $cropHeight = $cropWidth;
        }

        $cropWidth = ($this->currentDimensions['width'] < $cropWidth) ? $this->currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->currentDimensions['height'] < $cropHeight) ? $this->currentDimensions['height'] : $cropHeight;

        $cropX = intval(($this->currentDimensions['width'] - $cropWidth) / 2);
        $cropY = intval(($this->currentDimensions['height'] - $cropHeight) / 2);

        return $this->crop($cropX, $cropY, $cropWidth, $cropHeight);
    }

    /**
     * @inheritdoc
     */
    public function crop($startX, $startY, $cropWidth, $cropHeight)
    {
        // validate input
        if (!is_numeric($startX)) {
            throw new InvalidArgumentException('$startX must be numeric');
        }

        if (!is_numeric($startY)) {
            throw new InvalidArgumentException('$startY must be numeric');
        }

        if (!is_numeric($cropWidth)) {
            throw new InvalidArgumentException('$cropWidth must be numeric');
        }

        if (!is_numeric($cropHeight)) {
            throw new InvalidArgumentException('$cropHeight must be numeric');
        }

        $this->imagick->setIteratorIndex(0);
        while (true) {
            $this->imagick->cropImage($cropWidth, $cropHeight, $startX, $startY);
            if (!$this->imagick->nextImage()) {
                break;
            }
        }
        $this->currentDimensions['width'] = $this->imagick->getImageWidth();
        $this->currentDimensions['height'] = $this->imagick->getImageHeight();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function rotate($degrees)
    {
        if (!is_numeric($degrees)) {
            throw new InvalidArgumentException('$degrees must be numeric');
        }

        if (!function_exists('imagerotate')) {
            throw new RuntimeException('Your version of GD does not support image rotation.');
        }

        $this->imagick->setIteratorIndex(0);
        while (true) {
            $this->imagick->rotateImage(new \ImagickPixel(), $degrees);
            if (!$this->imagick->nextImage()) {
                break;
            }
        }
        $this->currentDimensions['width'] = $this->imagick->getImageWidth();
        $this->currentDimensions['height'] = $this->imagick->getImageHeight();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function background($color, $opacity = 100)
    {
        $opacity = $opacity / 100;
        if (strlen($color) > 1 && strpos($color, '#') === 0) {
            $color = substr($color, 1);
		}

        if (strlen($color) === 3) {
            $color = preg_replace("/./", "$0$0", $color);
		}

        $colors = array_map("hexdec", str_split($color, 2));
		$pixel1 = new \ImagickPixel(sprintf("rgb(%d, %d, %d)", $colors[0], $colors[1], $colors[2]));
		$pixel2 = new \ImagickPixel("transparent");
		$background = new \Imagick();

		$this->imagick->setIteratorIndex(0);
        while (true) {
            $background->newImage($this->currentDimensions['width'], $this->currentDimensions['height'], $pixel1);
            if (!$background->getImageAlphaChannel()) {
                $background->setImageAlphaChannel(constant("Imagick::ALPHACHANNEL_SET"));
			}
            $background->setImageBackgroundColor($pixel2);
			$background->evaluateImage(
			    constant("Imagick::EVALUATE_MULTIPLY"),
                $opacity,
                constant("Imagick::CHANNEL_ALPHA")
            );
			$background->setColorspace($this->imagick->getColorspace());
			$background->compositeImage($this->imagick, constant("Imagick::COMPOSITE_DISSOLVE"), 0, 0);

            if (!$this->imagick->nextImage()) {
                break;
            }
        }

		$this->imagick->clear();
		$this->imagick->destroy();
		$this->imagick = $background;
        $this->imagick->setImageFormat($this->format);
        $this->imagick->setFormat($this->format);

		return $this;
    }

    /**
     * @inheritdoc
     */
    public function show($rawData = false)
    {
        if (!$rawData && headers_sent()) {
            throw new RuntimeException('Cannot show image, headers have already been sent');
        }

        switch ($this->format) {
            case 'GIF':
                if (!$rawData) {
                    header('Content-type: image/gif');
                }
                break;
            case 'JPG':
            case 'JPEG':
                if (!$rawData) {
                    header('Content-type: image/jpeg');
                }
                break;
            case 'PNG':
                if (!$rawData) {
                    header('Content-type: image/png');
                }
                break;
            case 'WEBP':
                if (!$rawData) {
                    header('Content-type: image/webp');
                }
                break;
        }

        echo $this->imagick->getImageBlob();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function save($fileName)
    {
        $format = strtoupper(pathinfo($fileName, \PATHINFO_EXTENSION));

        if (!in_array($format, $this->allowFormats)) {
            throw new InvalidArgumentException ('Invalid format type specified in save function: ' . $format);
        }

        // make sure the directory is writeable
        if (!is_writeable(dirname($fileName))) {
            throw new RuntimeException ('File not writeable: ' . $fileName);
        }

        switch ($format) {
            case 'GIF':
                $this->imagick->optimizeImageLayers();
                break;
            case 'WEBP':
            case 'JPG':
            case 'JPEG':
                $this->imagick->setImageCompressionQuality($this->options['quality']);
                break;
        }

        $this->imagick->writeImage($fileName);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->imagick->getImageBlob();
    }

    /**
     * Class Destructor
     *
     */
    public function __destruct()
    {
        if ($this->imagick instanceof \Imagick) {
            $this->imagick->clear();
			$this->imagick->destroy();
		}
    }
}
