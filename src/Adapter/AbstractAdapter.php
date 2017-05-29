<?php
namespace Kof\Thumb\Adapter;

use Kof\Thumb\Exception\InvalidArgumentException;
use Kof\Thumb\Exception\UnexpectedValueException;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * The name of the file we're manipulating
     *
     * This must include the path to the file (absolute paths recommended)
     *
     * @var string
     */
    protected $fileName;

    /**
     * What the file format is (mime-type)
     *
     * @var string
     */
    protected $format;

    /**
     * The Allow Formats
     * @var array
     */
    protected $allowFormats = array('GIF', 'JPG', 'JPEG', 'PNG', 'WEBP');

    /**
     * Whether or not the current image is an actual file, or the raw file data
     *
     * By "raw file data" it's meant that we're actually passing the result of something
     * like file_get_contents() or perhaps from a database blob
     *
     * @var bool
     */
    protected $isDataStream;

    /**
     * The current dimensions of the image
     *
     * @var array
     */
    protected $currentDimensions = array(
        'width' => 0,
        'height' => 0
    );

    /**
     * The new, calculated dimensions of the image
     *
     * @var array
     */
    protected $newDimensions;

    /**
     * The maximum width an image can be after resizing (in pixels)
     *
     * @var int
     */
    protected $maxWidth;

    /**
     * The maximum height an image can be after resizing (in pixels)
     *
     * @var int
     */
    protected $maxHeight;

    /**
     * The percentage to resize the image by
     *
     * @var int
     */
    protected $percent;

    /**
     * The options for this class
     *
     * This array contains various options that determine the behavior in
     * various functions throughout the class.  Functions note which specific
     * option key / values are used in their documentation
     *
     * @var array
     */
    protected $options = array(
        'resizeUp' => false,
        'quality' => 100,
        'preserveAlpha' => true,
        'preserveTransparency' => true
    );

    /**
     * AbstractAdapter constructor.
     * @throws UnexpectedValueException
     * @param string $fileName
     * @param array|null $options
     * @param bool $isDataStream
     */
    public function __construct($fileName, array $options = null, $isDataStream = false)
    {
        $this->fileName = $fileName;
        $this->isDataStream = $isDataStream;

        if (!$this->isDataStream) {
            if (!file_exists($this->fileName)) {
                throw new UnexpectedValueException('Image file not found: ' . $this->fileName);
            } elseif (!is_readable($this->fileName)) {
                throw new UnexpectedValueException('Image file not readable: ' . $this->fileName);
            }
        }

        $imagesize = $this->isDataStream ? getimagesizefromstring($this->fileName) : getimagesize($this->fileName);
        $mime = isset($imagesize['mime']) ? $imagesize['mime'] : null;
        switch ($mime) {
            case 'image/gif':
                $this->format = 'GIF';
                break;
            case 'image/jpeg':
                $this->format = 'JPG';
                break;
            case 'image/png':
                $this->format = 'PNG';
                break;
            case 'image/webp':
                $this->format = 'WEBP';
                break;
            default:
                throw new UnexpectedValueException('Image format not supported: ' . $mime);
        }
        $this->currentDimensions = array(
            'width' => isset($imagesize[0]) ? $imagesize[0] : 0,
            'height' => isset($imagesize[1]) ? $imagesize[1] : 0,
        );

        $this->setOptions($options);
    }

    /**
     * @param string $index
     * @param mixed $defaultValue
     */
    public function getOption($index, $defaultValue = null)
    {
        return isset($this->options[$index]) ? $this->options[$index] : $defaultValue;
    }

    /**
     * @param string $index
     * @param mixed $defaultValue
     * @return self
     */
    public function setOption($index, $value)
    {
        $this->options[$index] = $value;

        return $this;
    }

    /**
     * Sets $this->options to $options
     *
     * @param array|null $options
     * @return self
     */
    public function setOptions(array $options = null)
    {
        if ($options) {
            $this->options = array_merge($this->options, $options);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getWidth()
    {
        return $this->currentDimensions['width'];
    }

    /**
     * @inheritdoc
     */
    public function getHeight()
    {
        return $this->currentDimensions['height'];
    }

    /**
     * Calculates the new image dimensions
     *
     * These calculations are based on both the provided dimensions and $this->maxWidth and $this->maxHeight
     *
     * @param int $width
     * @param int $height
     */
    protected function calcImageSize($width, $height)
    {
        $newSize = array(
            'newWidth' => $width,
            'newHeight' => $height
        );

        if ($this->maxWidth > 0) {
            $newSize = $this->calcWidth($width, $height);

            if ($this->maxHeight > 0 && $newSize['newHeight'] > $this->maxHeight) {
                $newSize = $this->calcHeight($newSize['newWidth'], $newSize['newHeight']);
            }
        }

        if ($this->maxHeight > 0) {
            $newSize = $this->calcHeight($width, $height);

            if ($this->maxWidth > 0 && $newSize['newWidth'] > $this->maxWidth) {
                $newSize = $this->calcWidth($newSize['newWidth'], $newSize['newHeight']);
            }
        }

        $this->newDimensions = $newSize;
    }

    /**
     * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
     *
     * @return array
     * @param int $width
     * @param int $height
     */
    protected function calcWidth($width, $height)
    {
        $newWidthPercentage = (100 * $this->maxWidth) / $width;
        $newHeight = ($height * $newWidthPercentage) / 100;

        return array(
            'newWidth' => intval($this->maxWidth),
            'newHeight' => intval($newHeight)
        );
    }

    /**
     * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
     *
     * @return array
     * @param int $width
     * @param int $height
     */
    protected function calcHeight($width, $height)
    {
        $newHeightPercentage = (100 * $this->maxHeight) / $height;
        $newWidth = ($width * $newHeightPercentage) / 100;

        return array(
            'newWidth' => ceil($newWidth),
            'newHeight' => ceil($this->maxHeight)
        );
    }

    /**
     * Calculates new image dimensions, not allowing the width and height to be less than either the max width or height
     *
     * @param int $width
     * @param int $height
     */
    protected function calcImageSizeStrict($width, $height)
    {
        $newDimensions = array(
            'newWidth' => 0,
            'newHeight' => 0
        );
        // first, we need to determine what the longest resize dimension is..
        if ($this->maxWidth >= $this->maxHeight) {
            // and determine the longest original dimension
            if ($width > $height) {
                $newDimensions = $this->calcHeight($width, $height);

                if ($newDimensions['newWidth'] < $this->maxWidth) {
                    $newDimensions = $this->calcWidth($width, $height);
                }
            } elseif ($height >= $width) {
                $newDimensions = $this->calcWidth($width, $height);

                if ($newDimensions['newHeight'] < $this->maxHeight) {
                    $newDimensions = $this->calcHeight($width, $height);
                }
            }
        } elseif ($this->maxHeight > $this->maxWidth) {
            if ($width >= $height) {
                $newDimensions = $this->calcWidth($width, $height);

                if ($newDimensions['newHeight'] < $this->maxHeight) {
                    $newDimensions = $this->calcHeight($width, $height);
                }
            } elseif ($height > $width) {
                $newDimensions = $this->calcHeight($width, $height);

                if ($newDimensions['newWidth'] < $this->maxWidth) {
                    $newDimensions = $this->calcWidth($width, $height);
                }
            }
        }

        $this->newDimensions = $newDimensions;
    }

    /**
     * Calculates new dimensions based on $this->percent and the provided dimensions
     *
     * @param int $width
     * @param int $height
     */
    protected function calcImageSizePercent($width, $height)
    {
        if ($this->percent > 0) {
            $this->newDimensions = $this->calcPercent($width, $height);
        }
    }

    /**
     * Calculates a new width and height for the image based on $this->percent and the provided dimensions
     *
     * @return array
     * @param int $width
     * @param int $height
     */
    protected function calcPercent ($width, $height)
    {
        $newWidth	= ($width * $this->percent) / 100;
        $newHeight	= ($height * $this->percent) / 100;

        return array(
            'newWidth'	=> ceil($newWidth),
            'newHeight'	=> ceil($newHeight)
        );
    }
}
