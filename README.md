# plg_system_lessghsvs
Joomla system plugin. PHP LESS compiling to CSS.

- I use it for old templates. Therefore: Adapted for my needs.
- Doesn't work with less files that contain JavaScript calculations (often seen in older J51 templates that use LESS).
- Used lessphp library has been bug fixed several times. Last workout was for PHP 8.1.
- Accepts only relative paths to less files inside template folder!
- Outputs CSS files only in paths inside template folder!
- Errors can be logged into file `plgSystemLessghsvs.php` in globally configured `log_path` of your site.

Test it in your environment before you use it. I seldomly use it in production environments.

Creates:
- unminified *.css
- minified *.min.css
- *.min.gz
- 1 temporary CSS backup file per run per less file. Bak file will be overriden with next run! Example: `mod_downloadcardsghsvs.css.plg_system_lessghsvs.bak`.

-----------------------------------------------------

# My personal build procedure (WSL 1 or 2, Debian, Win 10)

**@since Build procedure uses local repo fork of https://github.com/GHSVS-de/buildKramGhsvs**

- Prepare/adapt `./package.json`.
  - Don't forget versionSub. Special handling in this repo.
- `cd /mnt/z/git-kram/plg_system_lessghsvs`

## node/npm updates/installation
If not done yet:
- `npm install` (if needed)
### Update
- `npm run updateCheck` or (faster) `npm outdated`
- `npm run update` (if needed) or (faster) `npm update --save-dev`

## Build installable ZIP package
- `node build.js`
- New, installable ZIP is in `./dist` afterwards.
- All packed files for this ZIP can be seen in `./package`. **But only if you disable deletion of this folder at the end of `build.js`**.

### For Joomla update and changelog server
- Create new release with new tag.
- - See and copy and complete release description in `dist/release_no-changelog.txt`.
- Extracts(!) of the update and changelog XML for update and changelog servers are in `./dist` as well. Copy/paste and make necessary additions.
