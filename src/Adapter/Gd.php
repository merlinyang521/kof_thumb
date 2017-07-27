<?php
namespace Kof\Thumb\Adapter;

use Kof\Thumb\Exception\InvalidArgumentException;
use Kof\Thumb\Exception\RuntimeException;

class Gd extends AbstractAdapter
{
    /**
     * The prior image (before manipulation)
     *
     * @var resource
     */
    protected $oldImage;

    /**
     * The working image (used during manipulation)
     *
     * @var resource
     */
    protected $workingImage;

    /**
     * Gd constructor.
     * @param string $fileName
     * @param array|null $options
     * @param bool $isDataStream
     */
    public function __construct($fileName, array $options = null, $isDataStream = false)
    {
        parent::__construct($fileName, $options, $isDataStream);

        if ($isDataStream) {
            $this->oldImage = imagecreatefromstring($this->fileName);
        } else {
            switch ($this->format) {
                case 'GIF':
                    $this->oldImage = imagecreatefromgif($this->fileName);
                    break;
                case 'JPG':
                case 'JPEG':
                    $this->oldImage = imagecreatefromjpeg($this->fileName);
                    break;
                case 'PNG':
                    $this->oldImage = imagecreatefrompng($this->fileName);
                    break;
                case 'WEBP':
                    $this->oldImage = imagecreatefromwebp($this->fileName);
                    break;
            }
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

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        } else {
            $this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        }

        $this->preserveAlpha();

        // and create the newly sized image
        imagecopyresampled(
            $this->workingImage,
            $this->oldImage,
            0,
            0,
            0,
            0,
            $this->newDimensions['newWidth'],
            $this->newDimensions['newHeight'],
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        );

        // update all the variables and resources to be correct
        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $this->newDimensions['newWidth'];
        $this->currentDimensions['height'] = $this->newDimensions['newHeight'];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function copymerge(AdapterInterface $mergeAdapter, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct = 100)
    {
        imagecopymerge(
            $this->oldImage,
            imagecreatefromstring($mergeAdapter->__toString()),
            $dst_x,
            $dst_y,
            $src_x,
            $src_y,
            $src_w,
            $src_h,
            $pct
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function ttftext($size, $angle, $x, $y, array $color, $fontfile, $text)
    {
        imagettftext(
            $this->oldImage,
            $size,
            $angle,
            $x,
            $y,
            call_user_func_array('imagecolorallocate', array_merge([$this->oldImage], $color)),
            $fontfile,
            $text
        );

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

        $this->preserveAlpha();

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

        imagecopyresampled(
            $this->workingImage,
            $this->oldImage,
            0,
            0,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        // update all the variables and resources to be correct
        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $this->maxWidth;
        $this->currentDimensions['height'] = $this->maxHeight;

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

        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        } else {
            $this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        }

        $this->preserveAlpha();

        ImageCopyResampled(
            $this->workingImage,
            $this->oldImage,
            0,
            0,
            0,
            0,
            $this->newDimensions['newWidth'],
            $this->newDimensions['newHeight'],
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        );

        $this->oldImage = $this->workingImage;
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

        // do some calculations
        $cropWidth = ($this->currentDimensions['width'] < $cropWidth) ? $this->currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->currentDimensions['height'] < $cropHeight) ? $this->currentDimensions['height'] : $cropHeight;

        // ensure everything's in bounds
        if (($startX + $cropWidth) > $this->currentDimensions['width']) {
            $startX = ($this->currentDimensions['width'] - $cropWidth);
        }

        if (($startY + $cropHeight) > $this->currentDimensions['height']) {
            $startY = ($this->currentDimensions['height'] - $cropHeight);
        }

        if ($startX < 0) {
            $startX = 0;
        }

        if ($startY < 0) {
            $startY = 0;
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($cropWidth, $cropHeight);
        } else {
            $this->workingImage = imagecreate($cropWidth, $cropHeight);
        }

        $this->preserveAlpha();

        imagecopyresampled(
            $this->workingImage,
            $this->oldImage,
            0,
            0,
            $startX,
            $startY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $cropWidth;
        $this->currentDimensions['height'] = $cropHeight;

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

        $this->workingImage = imagerotate($this->oldImage, $degrees, 0);

        $newWidth = $this->currentDimensions['height'];
        $newHeight = $this->currentDimensions['width'];
        $this->oldImage = $this->workingImage;
        $this->currentDimensions['width'] = $newWidth;
        $this->currentDimensions['height'] = $newHeight;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function background($color, $opacity = 100)
    {
        $opacity = ($opacity * 127 / 100) - 127;
        if (strlen($color) > 1 && strpos($color, '#') === 0) {
            $color = substr($color, 1);
        }

        if (strlen($color) === 3) {
            $color = preg_replace("/./", "$0$0", $color);
        }

        $colors = array_map("hexdec", str_split($color, 2));

        $background = imagecreatetruecolor($this->currentDimensions['width'], $this->currentDimensions['height']);
		imagealphablending($background, false);
		imagesavealpha($background, true);
        imagecolorallocatealpha($background, $colors[0], $colors[1], $colors[2], $opacity);
		imagealphablending($background, true);

		if (imagecopy(
		    $background,
            $this->oldImage,
            0,
            0,
            0,
            0,
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        )) {
            imagedestroy($this->oldImage);
			$this->oldImage = $background;
		}

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
                imagegif($this->oldImage);
                break;
            case 'JPG':
            case 'JPEG':
                if (!$rawData) {
                    header('Content-type: image/jpeg');
                }
                imagejpeg($this->oldImage, null, $this->options['quality']);
                break;
            case 'PNG':
                if (!$rawData) {
                    header('Content-type: image/png');
                }
                imagepng($this->oldImage);
                break;
            case 'WEBP':
                if (!$rawData) {
                    header('Content-type: image/webp');
                }
                imagewebp($this->oldImage, null, $this->options['quality']);
                break;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function save($fileName, $format = null)
    {
        $format = ($format !== null) ? strtoupper($format) : $this->format;

        if (!in_array($format, $this->allowFormats)) {
            throw new InvalidArgumentException ('Invalid format type specified in save function: ' . $format);
        }

        // make sure the directory is writeable
        if (!is_writeable(dirname($fileName))) {
            throw new RuntimeException ('File not writeable: ' . $fileName);
        }

        switch ($format) {
            case 'GIF':
                imagegif($this->oldImage, $fileName);
                break;
            case 'JPG':
            case 'JPEG':
                imagejpeg($this->oldImage, $fileName, $this->options['quality']);
                break;
            case 'PNG':
                imagepng($this->oldImage, $fileName);
                break;
            case 'WEBP':
                imagewebp($this->oldImage, $fileName, $this->options['quality']);
                break;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        $data = null;
        ob_start();
        $this->show(true);
        $data = ob_get_contents();
        ob_end_clean();

        return $data;
    }

    /**
     * Class Destructor
     *
     */
    public function __destruct()
    {
        if (is_resource($this->oldImage)) {
            imagedestroy($this->oldImage);
        }

        if (is_resource($this->workingImage)) {
            imagedestroy($this->workingImage);
        }
    }

    /**
     * Preserves the alpha or transparency for PNG and GIF files
     *
     * Alpha / transparency will not be preserved if the appropriate options are set to false.
     * Also, the GIF transparency is pretty skunky (the results aren't awesome), but it works like a
     * champ... that's the nature of GIFs tho, so no huge surprise.
     *
     * This functionality was originally suggested by commenter Aimi (no links / site provided) - Thanks! :)
     *
     */
    protected function preserveAlpha()
    {
        if ($this->format == 'PNG' && $this->options['preserveAlpha'] === true) {
            imagealphablending($this->workingImage, false);

            $colorTransparent = imagecolorallocatealpha(
                $this->workingImage,
                255,
                255,
                255,
                0
            );

            imagefill($this->workingImage, 0, 0, $colorTransparent);
            imagesavealpha($this->workingImage, true);
        }
        // preserve transparency in GIFs... this is usually pretty rough tho
        if ($this->format == 'GIF' && $this->options['preserveTransparency'] === true) {
            $colorTransparent = imagecolorallocate(
                $this->workingImage,
                0,
                0,
                0
            );

            imagecolortransparent($this->workingImage, $colorTransparent);
            imagetruecolortopalette($this->workingImage, true, 256);
        }
    }
}
