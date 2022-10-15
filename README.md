# plg_system_lessghsvs
Joomla system plugin. PHP LESS compiling to CSS.

I use it for old templates. Therefore: Adapted for my needs.

Doesn't work with less files that contain JavaScript calculations (often seen in older J51 templates that use LESS).

Used lessphp library has been bug fixed several times. Last workout was for PHP 8.1.

Test it in our environment before you use it. I seldomly use it in production environmants.

Creates:
- unminified *.css
- minified *.min.css
- *.min.gz

##
`cd /mnt/z/git-kram/plg_system_lessghsvs/`

## composer
- The composer.json is located in folder `./_composer`
- Check for PHP libraries updates.

```
cd _composer/

composer outdated

OR

composer show -l
```
- both commands accept the parameter `--direct` to show only direct dependencies in the listing
- If somethig to bump/update:

```
composer update

OR

composer install
```
