Parse INI
==================

ParseINI adalah library PHP untuk mempersing [INI File Format][1]. File dot INI
adalah [salah satu file][2] yang digunakan untuk menyimpan configuration. 
Library ini di-design untuk terintegrasi dengan Class [Configuration Editor][3]. 
Untuk kebutuhan unparse/dump array kedalam format dot ini, Anda dapat 
menggunakan Class Configuration Editor.

[1]: https://en.wikipedia.org/wiki/INI_file
[2]: https://en.wikipedia.org/wiki/Configuration_file
[3]: https://github.com/ijortengab/configuration-editor

## Requirements

 - PHP > 5.4
 - ```composer require psr/log```

## Comparison

PHP telah memiliki fungsi untuk memparsing file dot ini. Library ParseINI hadir
untuk melengkapi berbagai kasus yang tidak dapat dihandle oleh fungsi bawaan
PHP (```parse_ini_file``` dan ```parse_ini_string```). Contohnya pada format
sbb:

```ini
key[child][] = value
key[child][] = other
```

Format diatas terinspirasi pada file [dot info][4] dari Drupal 7.

[4]: https://www.drupal.org/node/542202

## Repository

Tambahkan code berikut pada composer.json jika project anda membutuhkan library
ini. Perhatikan _trailing comma_ agar format json anda tidak rusak.

```json
{
    "require": {
        "ijortengab/parse-ini": "master"
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ijortengab/parse-ini"
        }
    ]
}
```

## Usage

```php
// Melalui file
$obj = new ParseINI;
$obj->filename = 'test.ini';
$obj->parse();
$result = $obj->data;

// Melalui string
$string = file_get_contents('test.ini');
$obj = new ParseINI;
$obj->raw = $string;
$obj->parse();
$result = $obj->data;
```
