<?php
namespace Kof\Thumb\Adapter;

use Kof\Thumb\Exception\InvalidArgumentException;
use Kof\Thumb\Exception\RuntimeException;

interface AdapterInterface
{
    /**
     * Resizes an image to be no larger than $maxWidth or $maxHeight
     *
     * If either param is set to zero, then that dimension will not be considered as a part of the resize.
     * Additionally, if $this->options['resizeUp'] is set to true (false by default), then this function will
     * also scale the image up to the maximum dimensions provided.
     *
     * @throws InvalidArgumentException
     * @param int $maxWidth The maximum width of the image in pixels
     * @param int $maxHeight The maximum height of the image in pixels
     * @return self
     */
    public function resize($maxWidth = 0, $maxHeight = 0);

    /**
     * Copy and merge part of an image
     *
     * @param AdapterInterface $mergeAdapter
     * @param int $dst_x
     * @param int $dst_y
     * @param int $src_x
     * @param int $src_y
     * @param int $src_w
     * @param int $src_h
     * @param int $pct
     * @return self
     */
    public function copymerge(AdapterInterface $mergeAdapter, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct = 100);

    /**
     * Write text to the image using TrueType fonts
     *
     * @param float $size
     * @param float $angle
     * @param int $x
     * @param int $y
     * @param array $color
     * @param string $fontfile
     * @param string $text
     * @return self
     */
    public function ttftext($size, $angle, $x, $y, array $color, $fontfile, $text);

    /**
     * Adaptively Resizes the Image
     *
     * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
     * remaining overflow (from the center) to get the image to be the size specified
     *
     * @throws InvalidArgumentException
     * @param int $maxWidth
     * @param int $maxHeight
     * @return self
     */
    public function adaptiveResize($width, $height);

    /**
     * Resizes an image by a given percent uniformly
     *
     * Percentage should be whole number representation (i.e. 1-100)
     *
     * @throws InvalidArgumentException
     * @param int $percent
     * @return self
     */
    public function resizePercent($percent = 0);

    /**
     * Crops an image from the center with provided dimensions
     *
     * If no height is given, the width will be used as a height, thus creating a square crop
     *
     * @throws InvalidArgumentException
     * @param int $cropWidth
     * @param int $cropHeight
     * @return self
     */
    public function cropFromCenter($cropWidth, $cropHeight = null);

    /**
     * Vanilla Cropping - Crops from x,y with specified width and height
     *
     * @throws InvalidArgumentException
     * @param int $startX
     * @param int $startY
     * @param int $cropWidth
     * @param int $cropHeight
     * @return self
     */
    public function crop($startX, $startY, $cropWidth, $cropHeight);

    /**
     * Rotates image specified number of degrees
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @param int $degrees
     * @return self
     */
    public function rotate($degrees);

    /**
     * Set the background color of an image
     *
     * @param string $color
     * @param int $opacity
     * @return mixed
     */
    public function background($color, $opacity = 100);

    /**
     * Shows an image
     *
     * This function will show the current image by first sending the appropriate header
     * for the format, and then outputting the image data. If headers have already been sent,
     * a runtime exception will be thrown
     *
     * @throws RuntimeException
     * @param bool $rawData Whether or not the raw image stream should be output
     * @return self
     */
    public function show($rawData = false);

    /**
     * get Image width
     *
     * @return int
     */
    public function getWidth();

    /**
     * get Image height
     *
     * @return int
     */
    public function getHeight();

    /**
     * Saves an image
     *
     * This function will make sure the target directory is writeable, and then save the image.
     *
     * If the target directory is not writeable, the function will try to correct the permissions (if allowed, this
     * is set as an option ($this->options['correctPermissions']).  If the target cannot be made writeable, then a
     * RuntimeException is thrown.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @param string $fileName The full path and filename of the image to save, must be one of [GIF,JPG,PNG,WEBP]
     * @return self
     */
    public function save($fileName);

    /**
     * Returns the Working Image as a String
     *
     * This function is useful for getting the raw image data as a string for storage in
     * a database, or other similar things.
     *
     * @return string
     */
    public function __toString();
}
