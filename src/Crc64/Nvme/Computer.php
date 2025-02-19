<?php

declare(strict_types=1);

namespace Awesomized\Checksums\Crc64\Nvme;

use Awesomized\Checksums;
use FFI;
use FFI\Exception;

/**
 * A wrapper around the CRC-64/NVME FFI library which calculates checksums at >20GiB/s on modern CPUs.
 *
 * Input of "123456789" (no quotes) should produce a checksum of 0xAE8B14860A799888.
 *
 * @link https://github.com/awesomized/crc64fast-nvme
 * @link https://reveng.sourceforge.io/crc-catalogue/all.htm#crc.cat.crc-64-nvme
 */
final class Computer implements Checksums\CrcInterface
{
    use Checksums\ChecksumTrait;

    private readonly FFI $crc64Nvme;

    private readonly FFI\CData $digestHandle;

    private static ?FFI $ffiAuto = null;

    /**
     * @param FFI|null $crc64Nvme The FFI instance for the CRC-64 NVMe library.
     *
     * @throws \InvalidArgumentException
     * @throws Exception
     */
    public function __construct(
        ?FFI $crc64Nvme = null,
    ) {
        $this->crc64Nvme = $crc64Nvme ?? self::getFfi();

        /**
         * @var FFI\CData $digestHandle
         *
         * @psalm-suppress UndefinedMethod - from FFI, we'll catch the Exception if the method is missing
         */
        // @phpstan-ignore-next-line
        $digestHandle = $this->crc64Nvme->digest_new();

        $this->digestHandle = $digestHandle;
    }

    public function write(
        string $string,
    ): self {
        try {
            /** @psalm-suppress UndefinedMethod - already checked this in the ctor */
            // @phpstan-ignore-next-line
            $this->crc64Nvme->digest_write(
                $this->digestHandle,
                $string,
                \strlen($string),
            );
        } catch (Exception $e) {
            throw new \RuntimeException(
                message: 'Could not write to the Digest handle. '
                . 'Is the library loaded, and has the digest_write() method?',
                previous: $e,
            );
        }

        return $this;
    }

    public function sum(): string
    {
        try {
            /**
             * @var int $crc64
             *
             * @psalm-suppress UndefinedMethod - already checked this in the ctor
             */
            // @phpstan-ignore-next-line
            $crc64 = $this->crc64Nvme->digest_sum64(
                $this->digestHandle,
            );
        } catch (Exception $e) {
            throw new \RuntimeException(
                message: 'Could not calculate the CRC-64 checksum. '
                . ' Is the library loaded, and has the digest_sum64() method?',
                previous: $e,
            );
        }

        return \sprintf(
            '%016x',
            $crc64,
        );
    }

    /**
     * @throws \InvalidArgumentException
     * @throws Exception
     */
    protected static function getFfi(): FFI
    {
        if (null !== self::$ffiAuto) {
            return self::$ffiAuto;
        }

        return self::$ffiAuto = Checksums\Crc64\Nvme\Ffi::fromAuto();
    }
}
