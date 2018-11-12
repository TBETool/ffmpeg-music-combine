<?php
/**
 * Created by PhpStorm.
 * User: anuj
 * Date: 18/10/18
 * Time: 5:00 PM
 */

namespace TBETool;


use Exception;
use getID3;

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
    public function combine($json_data)
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
            $input .= ' -i ' . $d->path;
        }

        /**
         * Generate filter complex with time in milliseconds
         */
        $f_c = '';
        $concat_list = '';
        $count = 0;
        foreach ($data as $key => $d) {
            // Get time gap between start time and end time
            $time_gap = $this->_getTimeGap($d);
            // Get duration of the audio file
            $audio_duration = $this->_getAudioDuration($d->path);

            // Check if audio duration is larger than time gap
            $audio_speed = $this->_getAudioSpeed($time_gap, $audio_duration);
            
            // Calculate delay = original_delay_seconds * audio_speed
            $delay_seconds = (flaot)$d->start * (float)($audio_speed['tempo_value']);

            $f_c .= '['.($key).']adelay='.$this->sToMs($delay_seconds).'|'.$this->sToMs($delay_seconds).','.$audio_speed['tempo'].'[o'.($key).'];';
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

        echo '*********MUSIC COMBINE SCRIPT::';
        echo $script;
        echo '::';
        
        $music_result = exec($script, $output_command, $return_command);

        if ($return_command === 0) {
            if (is_file($output_file))
                return $output_file;
            else
                throw new Exception('File not generated');
        } else {
            throw new Exception('Error appending music file: ' . $script);
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
     * Calculate time gap between the start and end time
     *
     * @param $d
     * @return mixed
     */
    private function _getTimeGap($d)
    {
        return (float)$d->end - $d->start;
    }

    /**
     * Calculate audio path using ID3 tag reader
     *
     * @param $audio_path
     */
    private function _getAudioDuration($audio_path)
    {
        try {
            $getID3 = new getID3;
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        $file = $getID3->analyze($audio_path);

        // Return 0 (minimum value for audio duration) if playtime_seconds does not exists.
        if (!key_exists('playtime_seconds', $file)) {
            return 0;
        }
        
        $duration = $file['playtime_seconds'];

        return $duration;
    }

    /**
     * Calculate audio speed to use with ffmpeg to speed the audio file
     * The speed should be between 0.5 to 2.0 where 0.5 is the 50% slow speed and 2.0 is the double speed
     *
     * @param $time_gap time gap in (float)seconds
     * @param $audio_duration duration of the audio file get from ID3 tag reader in (float) seconds
     */
    private function _getAudioSpeed($time_gap, $audio_duration)
    {
        if ($time_gap > $audio_duration) {
            return 'atempo=1.0';
        }

        $tempo = '';
        $tempo_value = 1;

//        $overdue_gap = $time_gap - $audio_duration;

        /**
         * Assume if time_gap should be of 10 seconds and audio_duration of 20 seconds
         * 20/10 will give 2 means the speed needs to be doubled
         *
         * If time_gap is 10 seconds and audio_duration of 15 seconds
         * 15/10 will give 1.5 means the speed needs to be 1.5 of the original speed
         *
         * If time_gap is 10 seconds and audio_duration of 25 seconds
         * 25/10 will give 2.5 means the speed needs to be doubled and then 0.5
         *
         * If time_gap is 10 seconds and audio_duration of 35 seconds
         * 35/10 will give 3.5 means the speed needs to be double and then 1.5
         *
         * If time_gap is 10 seconds and audio_duration of 45 seconds
         * 45/10 will give 4.5 means the speed needs to be double and then double and then 0.5
         */
        $speed_required = $audio_duration / $time_gap;

        if ($speed_required > 2) {
            $number_of_cycles_required = (int)$speed_required/2;

            $number_of_seconds_to_append = $speed_required - ($number_of_cycles_required * 2);

            for ($i = 0; $i < $number_of_cycles_required; $i++) {
                $tempo .= 'atempo=2.0,';
                $tempo_value *= 2;
            }

            if ($number_of_seconds_to_append > 0) {
                if ($number_of_seconds_to_append >= 1) {
                    $tempo .= 'atempo='.$number_of_seconds_to_append;
                    $tempo_value *= $number_of_seconds_to_append;
                } else {
                    $tempo .= 'atempo=1.0';
                    $tempo_value *= 1;
                }
            } else {
                $tempo = rtrim($tempo, ',');
            }
        } else {
            if ($speed_required < 1) {
                $tempo .= 'atempo=1.0';
                $tempo_value *= 1;
            } else {
                $tempo .= 'atempo=' . $speed_required;
                $tempo_value *= $speed_required;
            }
        }

        $response = [
            'tempo' => $tempo,
            'tempo_value' => $tempo_value
        ];
        return $response;
    }
}
