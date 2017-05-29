<?php
namespace Kof\Thumb;

use Kof\Thumb\Exception\InvalidArgumentException;
use Kof\Thumb\Adapter\AdapterInterface;

class Factory
{
    /**
     * @param string $fileName
     * @param array|null $options
     * @param bool $isDataStream
     * @param string|null $adapter
     * @return AdapterInterface
     */
    public static function create($fileName, array $options = null, $isDataStream = false, $adapter = null)
    {
        if ($adapter !== null && !in_array($adapter, array('Gd', 'Imagick'))) {
            throw new InvalidArgumentException('Invalid adapter: ' . $adapter);
        } elseif ($adapter === null) {
            if (extension_loaded('imagick')) {
                $adapter = 'Imagick';
            } elseif (extension_loaded('gd')) {
                $adapter = 'Gd';
            } else {
                throw new InvalidArgumentException(
                    'You must have either the GD or iMagick extension loaded to use this library'
                );
            }
        }

        $className = "Kof\\Thumb\\Adapter\\{$adapter}";
        return new $className($fileName, $options, $isDataStream);
    }
}
