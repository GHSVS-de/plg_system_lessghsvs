# plg_system_lessghsvs
Special version for ghsvs.de

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

`rm -r vendor/bin/`

`cp -r vendor ../`
