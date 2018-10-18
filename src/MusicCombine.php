<?php
/**
 * Created by PhpStorm.
 * User: anuj
 * Date: 18/10/18
 * Time: 5:00 PM
 */

namespace TBETool;


use Exception;
use getID3;       // https://github.com/JamesHeinrich/getID3

/**
 * Class MusicCombine
 * @package App\Library\AnujTools
 */
class MusicCombine
{
    private $FFMPEG;
    private $output_dir;
    private $music_script;

    /**
     * MusicLoop constructor.
     * @param $ffmpeg_path
     * @param $output_dir
     */
    function __construct($ffmpeg_path, $output_dir)
    {
        $this->FFMPEG = $ffmpeg_path;
        $this->output_dir = $output_dir;

        if (!is_dir($this->output_dir)) {
            mkdir($this->output_dir, 0777, true);
        }
    }

    /**
     * Combine multiple music files into one
     *
     * @param $json_data
     * @return string
     * @throws Exception
     */
    public function combine($json_data, $change_tempo = false)
    {
        if (!$json_data) {
            throw new Exception('Json data is empty');
        }
        /**
         * Json will be in array format containing following data nodes
         * path: string local path of the music file
         * start: start duration in seconds of the music file
         * end: end duration of the music file
         */
        $data = json_decode($json_data);

        if (!$data) {
            throw new Exception('Invalid json data. Please provide a valid json data');
        }

        /******************************************
         * Append each music to the blank music
         ******************************************/

        /**
         * Generate input files
         */
        $input = '';
        foreach ($data as $d) {
            /**
             * If change tempo is set to true,
             */
            if ($change_tempo) {
                $file = $this->_changeTempo($d);
            } else {
                $file = $d->path;
            }

            $input .= ' -i ' . $file;
        }

        /**
         * Generate filter complex with time in milliseconds
         */
        $f_c = '';
        $concat_list = '';
        $count = 0;
        foreach ($data as $key => $d) {
            $f_c .= '['.($key).']adelay='.$this->sToMs($d->start).'|'.$this->sToMs($d->start).'[o'.($key).'];';
            $concat_list .= '[o'.($key).']';
            $count += 1;
        }

        /**
         * Append concat string
         */
        $f_c_f = $f_c.$concat_list.'amix='.($count);

        /**
         * Generate final filter complex string
         */
        $filter_complex = '-filter_complex "'.$f_c_f.'"';

        /**
         * Output file
         */
        $out_file = 'music_' . time() . str_shuffle(time()) . '.mp3';
        $output_file = $this->output_dir . '/' . $out_file;

        /**
         * Script
         */
        $script = $this->FFMPEG . ' -y' . $input . ' ' . $filter_complex . ' ' . $output_file;

        /**
         * Set script to local variable
         */
        $this->music_script = $script;

        $music_result = exec($script, $output_command, $return_command);

        if ($return_command === 0) {
            if (is_file($output_file))
                return $output_file;
            else
                throw new Exception('File not generated');
        } else {
            throw new Exception('Error appending music file: ' . $music_result);
        }
    }

    /**
     * Convert seconds to milliseconds
     *
     * @param $seconds
     * @return float|int
     */
    private function sToMs($seconds)
    {
        return $seconds * 1000;
    }

    /**
     *
     * Change speed of the audio before adding to the input file
     * the speed of the audio will be in such a way that
     * it fits between the start and end time specified
     * for that audio if audio duration is longer than required
     *
     * @param $obj
     */
    private function _changeTempo($obj)
    {
        $getID3 = new getID3;
        $file = $getID3->analyze($obj->path);

        // Get duration in seconds in float
        // Ex. 6.3422112332434
        $duration = $file['playtime_seconds'];

        // Get duration required
        $duration_required = $obj->end - $obj->start;

        // If audio duration is less than required duration, return original audio
        if ($duration <= $duration_required) {
            return $obj->path;
        }

        // If audio duration is greater than required duration,
        // Speed up the audio fit the required duration
        // TODO: Implement audio speed


        return $obj->path;
    }
}
