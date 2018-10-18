# ffmpeg-music-combine
Combine multiple music files using FFMPE each music at specific start time.

---
### Requirement
* FFMPEG

### Using the Library

#### Installation

Intall library in PHP project using composer
```
composer require tbetool/ffmpeg-music-combine
```

#### Using Library
```
$music = new MusicCombine(FFMPEG_PATH, OUTPUT_DIR_PATH);
```

#### Combining music/audio files
Once object is created you can combine multiple audio files by sending audio files in json format like
```
$data = [
    [
        'path' => '/path/to/music/1.mp3',
        'start' => <start time in seconds>,
        'end' => <end time in seconds>
    ],
    [
        'path' => '/path/to/music/2.mp3',
        'start' => <start time in seconds>,
        'end' => <end time in seconds>
    ]
];

$final_music = $music->combine(json_encode($data));
```

This will return the final music path in which all music files are combined.

In case of any error which combining music files, it will throw an exception.

---

#### NOTE
* Only **mp3** file is supported. Help needed for for file type support.   
* Pass **absolute** path of the music file in `path` key.   
* Give **absolute** path of the FFMPEG installation. You can find the path by running `whereis ffmpeg` in the terminal.

---

### Exception Handling
_Ex:_
```
try {
    $final_music = $music->combine(json_encode($data));
} catch (Exception $exception) {
    echo $exception->getMessage();
}
```

---
### Bug Reporting

If you found any bug, create an [issue](https://github.com/TBETool/ffmpeg-music-combine/issues/new).

---
### Support and Contribution

Something is missing? 
* `Fork` the repositroy
* Make your contribution
* make a `pull request`

    
