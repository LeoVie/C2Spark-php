# C2Spark
C to Spark transpiler.

## Requirements
- GNAT (https://www.adacore.com/download/)
- Docker
- PHP >= 7.4
- Composer

## Setup
```bash
composer install
```

## Run
```bash
php c-to-ada.php [path_to_c_file] [output_directory]
```

### First run
The first time you run this program or always when you change the sources, you have to re-build the docker image.
For this, run the command mentioned above with the `--build` option:
 ```bash
 php c-to-ada.php [path_to_c_file] [output_directory] --build
 ```

### Example
```bash
php c-to-ada.php ./examples/subprogram-with-no-effect-and-invalid-division.c ./transpiled --build
```

## Tests
```bash
composer run test
```
