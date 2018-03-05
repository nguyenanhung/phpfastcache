<?php
/**
 *
 * This file is part of phpFastCache.
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt file.
 *
 * @author Khoa Bui (khoaofgod)  <khoaofgod@gmail.com> http://www.phpfastcache.com
 * @author Georges.L (Geolim4)  <contact@geolim4.com>
 *
 */
declare(strict_types=1);

namespace Phpfastcache\Core\Item;

use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use Phpfastcache\EventManager;
use Phpfastcache\Exceptions\{
  phpFastCacheInvalidArgumentException, phpFastCacheInvalidArgumentTypeException, phpFastCacheLogicException
};

/**
 * Class ItemExtendedTrait
 * @package phpFastCache\Core\Item
 * @property \Datetime $expirationDate Expiration date of the item
 * @property \Datetime $creationDate Creation date of the item
 * @property \Datetime $modificationDate Modification date of the item
 * @property mixed $data Data of the item
 * @property bool $fetched Fetch flag status
 * @property array $tags The tags array
 * @property array $removedTags The removed tags array
 * @property string $key The item key
 */
trait ItemExtendedTrait
{
    /********************
     *
     * PSR-6 Extended Methods
     *
     *******************/

    /**
     * @var EventManager
     */
    protected $eventManager;


    /**
     * @var ExtendedCacheItemPoolInterface
     */
    protected $driver;

    /**
     * @var string
     */
    protected $encodedKey;

    /**
     * Item constructor.
     * @param ExtendedCacheItemPoolInterface $driver
     * @param $key
     * @throws phpFastCacheInvalidArgumentException
     */
    public function __construct(ExtendedCacheItemPoolInterface $driver, $key)
    {
        if (\is_string($key)) {
            $this->key = $key;
            $this->driver = $driver;
            $this->driver->setItem($this);
            $this->expirationDate = new \DateTime();
            if($this->driver->getConfigOption('itemDetailedDate')){
                $this->creationDate = new \DateTime();
                $this->modificationDate = new \DateTime();
            }
        } else {
            throw new phpFastCacheInvalidArgumentTypeException('string', $key);
        }
    }

    /**
     * @return string
     */
    public function getEncodedKey(): string
    {
        if (!$this->encodedKey) {
            $keyHashFunction = $this->driver->getConfigOption('defaultKeyHashFunction');

            if ($keyHashFunction) {
                $this->encodedKey = $keyHashFunction($this->getKey());
            } else {
                $this->encodedKey = \md5($this->getKey());
            }
        }

        return $this->encodedKey;
    }

    /**
     * @return mixed
     */
    public function getUncommittedData()
    {
        return $this->data;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getExpirationDate(): \DateTimeInterface
    {
        return $this->expirationDate;
    }

    /**
     * Alias of expireAt() with forced $expiration param
     *
     * @param \DateTimeInterface $expiration
     *   The point in time after which the item MUST be considered expired.
     *   If null is passed explicitly, a default value MAY be used. If none is set,
     *   the value should be stored permanently or for as long as the
     *   implementation allows.
     *
     * @return ExtendedCacheItemInterface
     *   The called object.
     */
    public function setExpirationDate(\DateTimeInterface $expiration): ExtendedCacheItemInterface
    {
        return $this->expiresAt($expiration);
    }

    /**
     * @return \DateTimeInterface
     * @throws phpFastCacheLogicException
     */
    public function getCreationDate(): \DateTimeInterface
    {
        if ($this->driver->getConfig()->isItemDetailedDate()) {
            return $this->creationDate;
        }

        throw new phpFastCacheLogicException('Cannot access to the creation date when the "itemDetailedDate" configuration is disabled.');
    }

    /**
     * @param \DateTimeInterface $date
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheLogicException
     */
    public function setCreationDate(\DateTimeInterface $date): ExtendedCacheItemInterface
    {
        if ($this->driver->getConfig()->isItemDetailedDate()) {
            $this->creationDate = $date;
            return $this;
        }

        throw new phpFastCacheLogicException('Cannot access to the creation date when the "itemDetailedDate" configuration is disabled.');
    }

    /**
     * @return \DateTimeInterface
     * @throws phpFastCacheLogicException
     */
    public function getModificationDate(): \DateTimeInterface
    {
        if ($this->driver->getConfig()->isItemDetailedDate()) {
            return $this->modificationDate;
        }

        throw new phpFastCacheLogicException('Cannot access to the modification date when the "itemDetailedDate" configuration is disabled.');
    }

    /**
     * @param \DateTimeInterface $date
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheLogicException
     */
    public function setModificationDate(\DateTimeInterface $date): ExtendedCacheItemInterface
    {
        if ($this->driver->getConfig()->isItemDetailedDate()) {
            $this->modificationDate = $date;
            return $this;
        }

        throw new phpFastCacheLogicException('Cannot access to the modification date when the "itemDetailedDate" configuration is disabled.');
    }

    /**
     * @return int
     */
    public function getTtl(): int
    {
        return max(0, $this->expirationDate->getTimestamp() - time());
    }

    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expirationDate->getTimestamp() < (new \DateTime())->getTimestamp();
    }

    /**
     * @param int $step
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheInvalidArgumentException
     */
    public function increment($step = 1): ExtendedCacheItemInterface
    {
        if (\is_int($step)) {
            $this->fetched = true;
            $this->data += $step;
        } else {
            throw new phpFastCacheInvalidArgumentException('$step must be numeric.');
        }

        return $this;
    }

    /**
     * @param int $step
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheInvalidArgumentException
     */
    public function decrement($step = 1): ExtendedCacheItemInterface
    {
        if (\is_int($step)) {
            $this->fetched = true;
            $this->data -= $step;
        } else {
            throw new phpFastCacheInvalidArgumentException('$step must be numeric.');
        }

        return $this;
    }

    /**
     * @param array|string $data
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheInvalidArgumentException
     */
    public function append($data): ExtendedCacheItemInterface
    {
        if (\is_array($this->data)) {
            $this->data[] = $data;
        } else if (\is_string($data)) {
            $this->data .= (string)$data;
        } else {
            throw new phpFastCacheInvalidArgumentException('$data must be either array nor string.');
        }

        return $this;
    }


    /**
     * @param array|string $data
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheInvalidArgumentException
     */
    public function prepend($data): ExtendedCacheItemInterface
    {
        if (\is_array($this->data)) {
            \array_unshift($this->data, $data);
        } else if (\is_string($data)) {
            $this->data = (string)$data . $this->data;
        } else {
            throw new phpFastCacheInvalidArgumentException('$data must be either array nor string.');
        }

        return $this;
    }

    /**
     * @param $tagName
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheInvalidArgumentException
     */
    public function addTag($tagName): ExtendedCacheItemInterface
    {
        if (\is_string($tagName)) {
            $this->tags = \array_unique(\array_merge($this->tags, [$tagName]));

            return $this;
        }

        throw new phpFastCacheInvalidArgumentException('$tagName must be a string');
    }

    /**
     * @param array $tagNames
     * @return ExtendedCacheItemInterface
     */
    public function addTags(array $tagNames): ExtendedCacheItemInterface
    {
        foreach ($tagNames as $tagName) {
            $this->addTag($tagName);
        }

        return $this;
    }

    /**
     * @param array $tags
     * @return ExtendedCacheItemInterface
     * @throws phpFastCacheInvalidArgumentException
     */
    public function setTags(array $tags): ExtendedCacheItemInterface
    {
        if (\count($tags)) {
            if (\array_filter($tags, 'is_string')) {
                $this->tags = $tags;
            } else {
                throw new phpFastCacheInvalidArgumentException('$tagName must be an array of string');
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string $separator
     * @return string
     */
    public function getTagsAsString($separator = ', '): string
    {
        return \implode($separator, $this->tags);
    }

    /**
     * @param $tagName
     * @return ExtendedCacheItemInterface
     */
    public function removeTag($tagName): ExtendedCacheItemInterface
    {
        if (($key = array_search($tagName, $this->tags)) !== false) {
            unset($this->tags[ $key ]);
            $this->removedTags[] = $tagName;
        }

        return $this;
    }

    /**
     * @param array $tagNames
     * @return ExtendedCacheItemInterface
     */
    public function removeTags(array $tagNames): ExtendedCacheItemInterface
    {
        foreach ($tagNames as $tagName) {
            $this->removeTag($tagName);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getRemovedTags(): array
    {
        return \array_diff($this->removedTags, $this->tags);
    }

    /**
     * Return the data as a well-formatted string.
     * Any scalar value will be casted to an array
     * @param int $option \json_encode() options
     * @param int $depth \json_encode() depth
     * @return string
     */
    public function getDataAsJsonString($option = 0, $depth = 512): string
    {
        $data = $this->get();

        if (\is_object($data) || \is_array($data)) {
            $data = \json_encode($data, $option, $depth);
        } else {
            $data = \json_encode([$data], $option, $depth);
        }

        return \json_encode($data, $option, $depth);
    }

    /**
     * Implements \JsonSerializable interface
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->get();
    }


    /**
     * Set the EventManager instance
     *
     * @param EventManager $em
     * @return ExtendedCacheItemInterface
     */
    public function setEventManager(EventManager $em): ExtendedCacheItemInterface
    {
        $this->eventManager = $em;

        return $this;
    }


    /**
     * Prevent recursions for Debug (php 5.6+)
     * @return array
     */
    final public function __debugInfo()
    {
        $info = \get_object_vars($this);
        $info[ 'driver' ] = 'object(' . \get_class($info[ 'driver' ]) . ')';

        return (array)$info;
    }
}