Parse YML
==================

ParseYML adalah library PHP untuk mempersing [YAML File Format][1]. File dot YML
adalah [salah satu file][2] yang digunakan untuk menyimpan configuration. 
Library ini di-design untuk terintegrasi dengan Class [Configuration Editor][3]. 
Untuk kebutuhan unparse/dump array kedalam format dot yml, Anda dapat 
menggunakan Class Configuration Editor.

[1]: https://en.wikipedia.org/wiki/YAML
[2]: https://en.wikipedia.org/wiki/Configuration_file
[3]: https://github.com/ijortengab/configuration-editor

## Requirements

 - PHP > 5.4
 - ```composer require psr/log```

## Comparison

Library PHP untuk parsing format YAML yang sudah exists adalah [syck], [spyc], 
dan [symfony/yaml][4]. Perbedaan secara konsep dengan library lainnya ialah 
perlakuan terhadap *comments* (komentar berupa tulisan yang tidak termasuk dalam
konfigurasi) yang terdapat pada file YML. ParseYML jika digunakan bersama dengan
[ConfigurationEditor][3] tidak akan menghilangkan komentar tersebut.

[syck]: http://pecl.php.net/package/syck
[spyc]: https://github.com/mustangostang/spyc
[4]: http://symfony.com/doc/current/components/yaml/introduction.html

## Repository

Tambahkan code berikut pada composer.json jika project anda membutuhkan library
ini. Perhatikan _trailing comma_ agar format json anda tidak rusak.

```json
{
    "require": {
        "ijortengab/parse-yml": "master"
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ijortengab/parse-yml"
        }
    ]
}
```

## Usage

```php
// Melalui file
$obj = new ParseYML;
$obj->filename = 'test.yml';
$obj->parse();
$result = $obj->data;

// Melalui string
$string = file_get_contents('test.yml');
$obj = new ParseYML;
$obj->raw = $string;
$obj->parse();
$result = $obj->data;
```

## Exception
Disarankan untuk menghandle RuntimeException saat menjalankan method ::parse() 
apabila format diragukan kevalidasiannya.

Contoh:
```
try {
    $obj = new ParseYML;
    $obj->filename = 'test.yml';
    $obj->parse();
}
catch (\RuntimeException $e) {
}
```
