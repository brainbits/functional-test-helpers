<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\ZipContents;

use DateTimeImmutable;
use DateTimeZone;

use function array_key_exists;
use function fseek;
use function ftell;
use function function_exists;
use function iconv_strlen;
use function is_file;
use function is_resource;
use function mb_convert_encoding;
use function ord;
use function Safe\filesize;
use function Safe\fopen;
use function Safe\fread;
use function Safe\iconv;
use function Safe\rewind;
use function Safe\substr;
use function Safe\unpack;
use function strlen;
use function time;

final class ZipContents
{
    public function readFile(string $file): ZipInfo
    {
        if (!is_file($file)) {
            throw InvalidArchive::notAFile($file);
        }

        return $this->readStream(fopen($file, 'rb'), filesize($file));
    }

    /**
     * Read the contents of a ZIP archive
     *
     * This function lists the files stored in the archive, and returns an indexed array of FileInfo objects
     *
     * The archive is closed afer reading the contents, for API compatibility with TAR files
     * Reopen the file with open() again if you want to do additional operations
     *
     * @param resource $fh
     *
     * @return FileInfo[]
     *
     * @throws InvalidArchive
     */
    public function readStream($fh, int $size): ZipInfo
    {
        if (!is_resource($fh)) {
            throw InvalidArchive::notAStream($fh);
        }

        if (!$size) {
            throw InvalidArchive::zeroSize();
        }

        $centralDir = $this->readCentralDir($fh, $size);

        rewind($fh);
        fseek($fh, $centralDir['offset']);

        $fileInfos = [];
        for ($i = 0; $i < $centralDir['entries']; $i++) {
            $centralFile = $this->readCentralFileHeader($fh);
            $fileInfos[] = $fileInfo = FileInfo::fromCentralFileHeader($centralFile);
        }

        return ZipInfo::fromCentralDirHeader($size, $centralDir, $fileInfos);
    }

    /**
     * Read the central directory
     *
     * This key-value list contains general information about the ZIP file
     *
     * @param resource $fh
     *
     * @return mixed[]
     */
    private function readCentralDir($fh, int $size): array
    {
        $maximumSize = 277;
        if ($size < 277) {
            $maximumSize = $size;
        }

        fseek($fh, $size - $maximumSize);
        $pos = ftell($fh);
        $bytes = 0x00000000;

        while ($pos < $size) {
            $byte = fread($fh, 1);
            $bytes = (($bytes << 8) & 0xFFFFFFFF) | ord($byte);
            if ($bytes === 0x504b0506) {
                break;
            }

            $pos++;
        }

        $data = unpack(
            'vdisk/vdisk_start/vdisk_entries/ventries/Vsize/Voffset/vcomment_size',
            fread($fh, 18),
        );

        $centralDir['comment'] = null;
        if ($data['comment_size'] !== 0) {
            $centralDir['comment'] = fread($fh, $data['comment_size']);
        }

        $centralDir['entries'] = $data['entries'];
        $centralDir['disk_entries'] = $data['disk_entries'];
        $centralDir['offset'] = $data['offset'];
        $centralDir['disk_start'] = $data['disk_start'];
        $centralDir['size'] = $data['size'];
        $centralDir['disk'] = $data['disk'];

        return $centralDir;
    }

    /**
     * Read the next central file header
     *
     * Assumes the current file pointer is pointing at the right position
     *
     * @param resource $fh
     *
     * @return mixed[]
     */
    private function readCentralFileHeader($fh): array
    {
        $binaryData = fread($fh, 46);
        $header = unpack(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'vchkid/vid/vversion/vversion_extracted/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset',
            $binaryData,
        );

        $header['filename'] = null;
        if ($header['filename_len'] !== 0) {
            $header['filename'] = fread($fh, $header['filename_len']);
            if ($header['filename']) {
                $header['filename'] = $this->cpToUtf8($header['filename']);
            }
        }

        $header['extra'] = null;
        $header['extradata'] = [];
        if ($header['extra_len'] !== 0) {
            $header['extra'] = fread($fh, $header['extra_len']);
            $header['extradata'] = $this->parseExtra($header['extra']);
        }

        $header['comment'] = null;
        if ($header['comment_len'] !== 0) {
            $header['comment'] = fread($fh, $header['comment_len']);
            if ($header['comment']) {
                $header['comment'] = $this->cpToUtf8($header['comment']);
            }
        }

        $header['mtime'] = $this->makeUnixTime($header['mdate'], $header['mtime']);
        $header['stored_filename'] = $header['filename'];
        $header['status'] = 'ok';

        if (substr($header['filename'], -1) === '/') {
            $header['external'] = 0x41FF0010;
        }

        $header['folder'] = $header['external'] === 0x41FF0010 || $header['external'] === 16 ? 1 : 0;

        return $header;
    }

    /**
     * Parse the extra headers into fields
     *
     * @return mixed[]
     */
    private function parseExtra(string $header): array
    {
        $extra = [];
        // parse all extra fields as raw values
        while (strlen($header) !== 0) {
            $set = unpack('vid/vlen', $header);
            $header = substr($header, 4);
            $value = substr($header, 0, $set['len']);
            $header = substr($header, $set['len']);
            $extra[$set['id']] = $value;
        }

        // handle known ones
        if (array_key_exists(0x6375, $extra)) {
            $extra['utf8comment'] = substr($extra[0x7075], 5); // strip version and crc
        }

        if (array_key_exists(0x7075, $extra)) {
            $extra['utf8path'] = substr($extra[0x7075], 5); // strip version and crc
        }

        return $extra;
    }

    /**
     * Convert the given CP437 encoded string to UTF-8
     *
     * Tries iconv with the correct encoding first, falls back to mbstring with CP850 which is
     * similar enough. CP437 seems not to be available in mbstring. Lastly falls back to keeping the
     * string as is, which is still better than nothing.
     *
     * On some systems iconv is available, but the codepage is not. We also check for that.
     */
    private function cpToUtf8(string $string): string
    {
        if (function_exists('iconv') && iconv_strlen('', 'CP437') !== false) {
            return iconv('CP437', 'UTF-8', $string);
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, 'UTF-8', 'CP850');
        }

        return $string;
    }

    /**
     * Create a UNIX timestamp from a DOS timestamp
     */
    private function makeUnixTime(int|null $mdate = null, int|null $mtime = null): int
    {
        if ($mdate && $mtime) {
            $year = (($mdate & 0xFE00) >> 9) + 1980;
            $month = ($mdate & 0x01E0) >> 5;
            $day = $mdate & 0x001F;

            $hour = ($mtime & 0xF800) >> 11;
            $minute = ($mtime & 0x07E0) >> 5;
            $second = ($mtime & 0x001F) << 1;

            $mtime = (int) (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->setDate($year, $month, $day)
                ->setTime($hour, $minute, $second, 0)
                ->format('U');
        } else {
            $mtime = time();
        }

        return $mtime;
    }
}
