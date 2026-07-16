# Changelog

## Backup and Migrate 5.1.5, 2026-07-11

- [#3392461](https://www.drupal.org/project/backup_migrate/issues/3392461) by grimreaper, shreya_th, danrod: Using empty file as ZipArchive is deprecated.
- [#3357349](https://www.drupal.org/project/backup_migrate/issues/3357349) by damienmckenna, danrod, ivnish: Improved logging when something fails.
- [#3305094](https://www.drupal.org/project/backup_migrate/issues/3305094) by grevil, Anybody, tamerzg, nikhil_110, xmacinfo, danrod: Log "backup restored" to watchdog after restore finished.
- [#3357350](https://www.drupal.org/project/backup_migrate/issues/3357350) by damienmckenna, danrod: Allow forcing a scheduled backup to run via Drush.
- [#3320760](https://www.drupal.org/project/backup_migrate/issues/3320760) by tamerzg, damienmckenna, danrod: Add encryption option to backup schedule.
- [#3593967](https://www.drupal.org/project/backup_migrate/issues/3593967) by darren_oh, danrod: SQL mode mismatch possible.
- [#3357391](https://www.drupal.org/project/backup_migrate/issues/3357391) by akshay_kashyap, Ashu1864, grevil, koustav_mondal, jeremy1606, kieran.cott, danrod: Fix PHPStan issues.
- [#3564661](https://www.drupal.org/project/backup_migrate/issues/3564661) by ivnish, danrod, kieran.cott, schillerm, nitinkumar_7: Fix PHPCS issues.
- [#3357392](https://www.drupal.org/project/backup_migrate/issues/3357392) by akshay_kashyap, vimal_nadar, damienmckenna, ravi_kant, ivnish, yusuf_khan, danrod: Address coding standard issues in README.md file that trigger warning messages.
- [#3533320](https://www.drupal.org/project/backup_migrate/issues/3533320) by marlon.buella, ivnish, Anybody, danrod: Importing public files backup causes deprecated function error.
- [#3357357](https://www.drupal.org/project/backup_migrate/issues/3357357) by damienmckenna, Akshaykashyap, nikhil_110, danrod: Log message after backup succeeds.
- [#3577589](https://www.drupal.org/project/backup_migrate/issues/3577589) by astonvictor, Anybody, danrod: Caught class Drupal\backup_migrate\Drupal\Filter\Exception not found.
- [#3567236](https://www.drupal.org/project/backup_migrate/issues/3567236) by leducdubleuet, davps, Anybody, danrod: Email Settings are not saved.
- [#3548593](https://www.drupal.org/project/backup_migrate/issues/3548593) by anybody, LRWebks, ivnish: Add a setting to *dynamically* exclude data from cache tables and other unwanted sources.

## Backup and Migrate 5.1.0, 2024-11-19

- [#3223057](https://www.drupal.org/project/backup_migrate/issues/3223057) by paulocs, larisse, DamienMcKenna, Anybody, Grevil: Add Drush command for running a quick backup.
- [#2968766](https://www.drupal.org/project/backup_migrate/issues/2968766) by paulocs, DamienMcKenna, intrafusion, larisse, fgm, hron84: Add Drush commands for getting details of available configurations.
- [#3262387](https://www.drupal.org/project/backup_migrate/issues/3262387) by DamienMcKenna: Remove support for Drupal 8.
- [#3223058](https://www.drupal.org/project/backup_migrate/issues/3223058) by DamienMcKenna, paulocs, larisse: Add Drush command for running a scheduled backup.
- [#3258613](https://www.drupal.org/project/backup_migrate/issues/3258613) by Anybody, hmendes, pmagunia, DamienMcKenna: Improve Quick Backup status message.
- [#3262391](https://www.drupal.org/project/backup_migrate/issues/3262391) by DamienMcKenna: Coding standards improvements.
- [#3296607](https://www.drupal.org/project/backup_migrate/issues/3296607) by Project Update Bot, DamienMcKenna: Automated Drupal 10 compatibility fixes.
- By DamienMcKenna: Updated CHANGELOG.txt for recent 5.0.x changes already present in 5.1.x.
- [#2846650](https://www.drupal.org/project/backup_migrate/issues/2846650) by paulocs, Grevil, Dinu Rodnitchi, PhilY, aldairsoares, Anybody, ikit-claw, bruno.bicudo, DamienMcKenna: Bring back "Description / note" field to quick save tab.
- [#3256830](https://www.drupal.org/project/backup_migrate/issues/3256830) by hmendes, beatrizrodrigues, DamienMcKenna: Fix deprecation notices in tests.
- [#3236380](https://www.drupal.org/project/backup_migrate/issues/3236380) by gabrielda, larisse, DamienMcKenna: Send email on success.
- [#3216193](https://www.drupal.org/project/backup_migrate/issues/3216193) by hmendes, larisse, DamienMcKenna: Improve error handling when backup cannot be decrypted if the archive is encrypted.
- [#3169587](https://www.drupal.org/project/backup_migrate/issues/3169587) by codebymikey, DamienMcKenna, solideogloria: Warning: opendir(private://backup_migrate/scheduled): failed to open dir.
- [#3262070](https://www.drupal.org/project/backup_migrate/issues/3262070) by WagnerMelo, Anybody, Grevil, hmendes, andregp, DamienMcKenna, bruno.bicudo: Show date & time when restore / backup was successful.
- [#3390910](https://www.drupal.org/project/backup_migrate/issues/3390910) by sarwan_verma, kepesv, DamienMcKenna: Drupal 10 compatibility warnings - Call `\Drupal\Core\Entity\Query\QueryInterface::accessCheck()` with TRUE or FALSE to specify whether access should be checked.
- [#3433054](https://www.drupal.org/project/backup_migrate/issues/3433054) by benstallings, ankitv18, damienmckenna: Add GitLab CI.
- [#3438158](https://www.drupal.org/project/backup_migrate/issues/3438158) by Project Update Bot, grevil, vladimiraus, ankitv18, damienmckenna, markie, solideogloria, benstallings: Automated Drupal 11 compatibility fixes.
- [#3472560](https://www.drupal.org/project/backup_migrate/issues/3472560) by ankitv18, damienmckenna, solideogloria: Fix cspell pipeline.
- [#3228379](https://www.drupal.org/project/backup_migrate/issues/3228379) by grevil, jillh68, solideogloria, slejnej, anybody, damienmckenna, chris dart, jcnventura, jamesgrobertson, koffer, cristiroma, mrdrewkeller: Download errors, backup files contain trailing HTML, corrupts archive backups.
- [#3478354](https://www.drupal.org/project/backup_migrate/issues/3478354) by leslieg, damienmckenna: Add logo for compatibility with Project Browser.
- By DamienMcKenna: Disable the upgrade_status test.

## Backup and Migrate 5.0.x-dev, 2023-xx-xx

- [#3338192](https://www.drupal.org/project/backup_migrate/issues/3338192) by tamerzg, DamienMcKenna: `backup_migrate_update_5001` is never executed due to `CORE_MINIMUM_SCHEMA_VERSION`.

## Backup and Migrate 5.0.3, 2022-12-18

- [#3304922](https://www.drupal.org/project/backup_migrate/issues/3304922) by ressa, DamienMcKenna: Drupal 10: Fatal error, logger must be compatible with `Psr\Log\AbstractLogger`.
- [#3309272](https://www.drupal.org/project/backup_migrate/issues/3309272) by DamienMcKenna: Tests must define `$modules` as a protected variable.
- [#3268401](https://www.drupal.org/project/backup_migrate/issues/3268401) by DamienMcKenna, Mok7tar, robcarr, crutch, solideogloria, donpwinston: `filectime()` and `filesize()` receiving NULL values.
- [#3309301](https://www.drupal.org/project/backup_migrate/issues/3309301) by DamienMcKenna: Adjust site restore check to be less strict.
- [#3305241](https://www.drupal.org/project/backup_migrate/issues/3305241) by DamienMcKenna, WagnerMelo: Improve test coverage, fix D10 issues.
- [#3133933](https://www.drupal.org/project/backup_migrate/issues/3133933) by DamienMcKenna, ramonvasconcelos, larisse, paulocs, mrinalini9, alexanderj, solideogloria, pflora: Send email on failure.
- [#3258786](https://www.drupal.org/project/backup_migrate/issues/3258786) by DamienMcKenna, Universaldenker, keithn, elber, fchandler, Grevil: Using empty file as ZipArchive is deprecated.
- [#3247231](https://www.drupal.org/project/backup_migrate/issues/3247231) by DamienMcKenna, guille_rodri: Sort in queryFiles function.

## Backup and Migrate 5.0.2, 2022-08-19

- [#3261770](https://www.drupal.org/project/backup_migrate/issues/3261770) by hmendes, solideogloria, Adrianm6254, DamienMcKenna: PHP notice when downloading saved backup.
- [#3296606](https://www.drupal.org/project/backup_migrate/issues/3296606) by Project Update Bot, DamienMcKenna: Automated Drupal 10 compatibility fixes.
- [#3224457](https://www.drupal.org/project/backup_migrate/issues/3224457) by jcnventura, DamienMcKenna: Wrong type hint in DrupalTempFileAdapter.

## Backup and Migrate 5.0.1, 2021-07-09

- [#3220548](https://www.drupal.org/project/backup_migrate/issues/3220548) by paulocs, senzaesclusiva, DamienMcKenna: Warning: Invalid argument supplied for foreach() after CTools security update.
- [#3221856](https://www.drupal.org/project/backup_migrate/issues/3221856) by larisse, DamienMcKenna, paulocs, PranaliMW: In Schedule backup, "keep last backup" option not working.
- [#3221636](https://www.drupal.org/project/backup_migrate/issues/3221636) by hmendes, snehahande, DamienMcKenna: Most recent backups in saved backups tab.

## Backup and Migrate 5.0.0, 2021-06-14

- [#3177961](https://www.drupal.org/project/backup_migrate/issues/3177961) by DamienMcKenna, nikhitarathore: Full site backup doesn't work.
- [#3200694](https://www.drupal.org/project/backup_migrate/issues/3200694) by David Radcliffe, DamienMcKenna: Returning bool from comparison function is deprecated in PHP 8.0.
- [#3200714](https://www.drupal.org/project/backup_migrate/issues/3200714) by DiegoPino, pdcarto, azovsky, felixoe, DamienMcKenna: Incompatibility with s3fs.
- [#3186638](https://www.drupal.org/project/backup_migrate/issues/3186638) by bgilhome, DamienMcKenna: Schema for plugin 'config' keys.
- [#3186656](https://www.drupal.org/project/backup_migrate/issues/3186656) by fgm, DamienMcKenna: Use state API instead of custom KV collection for last run.
- [#3210689](https://www.drupal.org/project/backup_migrate/issues/3210689) by DamienMcKenna, MichaelvonB: Can't restore backups.
- [#3154716](https://www.drupal.org/project/backup_migrate/issues/3154716) by DamienMcKenna, xmacinfo: Unable to delete backups.
- [#3174581](https://www.drupal.org/project/backup_migrate/issues/3174581) by paulocs, kleiton_rodrigues, DamienMcKenna: `drupalPostForm` in functional tests is deprecated.
- [#3187818](https://www.drupal.org/project/backup_migrate/issues/3187818) by AndrewsizZ, DamienMcKenna: Replace uses of `REQUEST_TIME` and `time()` with time service.
- [#3136294](https://www.drupal.org/project/backup_migrate/issues/3136294) by Pooja Ganjage, ergophobe, DamienMcKenna, gagarine: Change Defuse message to a notice, not warning.
- [#3186486](https://www.drupal.org/project/backup_migrate/issues/3186486) by SivaprasadC, DamienMcKenna: Duplicated words and minor typos in a couple of files.
- [#3186527](https://www.drupal.org/project/backup_migrate/issues/3186527) by DamienMcKenna: Fix tests on 5.0.x branch.
- [#3214038](https://www.drupal.org/project/backup_migrate/issues/3214038) by DamienMcKenna: Discourage "full site" backup option.
- [#3197359](https://www.drupal.org/project/backup_migrate/issues/3197359) by larisse, bart lambert, DamienMcKenna, hmendes: Encryption fails to produce backup.
- [#3156794](https://www.drupal.org/project/backup_migrate/issues/3156794) by larisse, hmendes, pglatz, DamienMcKenna: Add token support to filename output.
- [#3217908](https://www.drupal.org/project/backup_migrate/issues/3217908) by DamienMcKenna: Inconsistent arguments on `alterBackupMigrate()`.
- [#3216400](https://www.drupal.org/project/backup_migrate/issues/3216400) by jacob.embree, larisse, DamienMcKenna: Rename `HTTPClientInterface.php` to `HttpClientInterface.php`.
- [#3216285](https://www.drupal.org/project/backup_migrate/issues/3216285) by paulocs, larisse, DamienMcKenna: PHPUnit 8 introduced void return types on `setUp()` methods.
- [#3180214](https://www.drupal.org/project/backup_migrate/issues/3180214) by larisse, djg_tram, DamienMcKenna: Site does not switch out of maintenance mode after backup finished.
- [#3218417](https://www.drupal.org/project/backup_migrate/issues/3218417) by DamienMcKenna, larisse: Update output header (incorrect URL, etc).

## Backup and Migrate 5.0.0-rc2, 2020-09-28

- [#3158790](https://www.drupal.org/project/backup_migrate/issues/3158790) by devad, raman.b, karolus, robcarr, DamienMcKenna, endless_wander, Bagz, jhnnsbstnbch, abdul_azeez_drusys, celia_ccs: Unable to add schedules and profiles.
- [#3169301](https://www.drupal.org/project/backup_migrate/issues/3169301) by devad, snehalgaikwad, DamienMcKenna: Unable to add backup source.
- [#3173623](https://www.drupal.org/project/backup_migrate/issues/3173623) by DamienMcKenna: Add test coverage for running a backup through cron in the UI.
- [#3164263](https://www.drupal.org/project/backup_migrate/issues/3164263) by devad, DamienMcKenna, bigmonmulgrew: Incompatible `PluginManager::call()` declaration.
- [#3173582](https://www.drupal.org/project/backup_migrate/issues/3173582) by DamienMcKenna: Replace `BACKUP_MIGRATE_MODULE_VERSION` constant with the module's actual version.
- [#3154715](https://www.drupal.org/project/backup_migrate/issues/3154715) by DamienMcKenna, xmacinfo: Add an update script to clear the caches for upgrading to 5.0.x.

## Backup and Migrate 5.0.0-rc1, 2020-06-12

- [#3051092](https://www.drupal.org/project/backup_migrate/issues/3051092) by DamienMcKenna, vuil, rpayanm, waverate: Drupal 9 compatibility.
- [#3065017](https://www.drupal.org/project/backup_migrate/issues/3065017) by DamienMcKenna, dercheffe, Promo-IL: Restore emoji unicode characters not possible.
- [#3143651](https://www.drupal.org/project/backup_migrate/issues/3143651) by DamienMcKenna: Ignore `cache_page` by default.
- [#3143652](https://www.drupal.org/project/backup_migrate/issues/3143652) by DamienMcKenna: Coding standards improvements; rename class methods that start with an underscore.
- [#3124193](https://www.drupal.org/project/backup_migrate/issues/3124193) by vsujeetkumar, DamienMcKenna, Neslee Canil Pinto: `t()` calls should be avoided in classes.
- [#3159638](https://www.drupal.org/project/backup_migrate/issues/3159638) by DamienMcKenna, endless_wander, snehalgaikwad, kvantstudio, naresh_bavaskar: Error when saving destination.

## Backup and Migrate 8.x-4.x-dev, xxxx-xx-xx

- [#3121797](https://www.drupal.org/project/backup_migrate/issues/3121797) by paulocs, thalles, RenatoG, Neslee Canil Pinto: Update `support.source` link in composer.json.
- [#3104422](https://www.drupal.org/project/backup_migrate/issues/3104422) by peterkokot, DamienMcKenna: PHP 7.4 deprecated reverse order of glue and pieces in implode.
- [#3121309](https://www.drupal.org/project/backup_migrate/issues/3121309) by DamienMcKenna, Neslee Canil Pinto: Compatibility with Drupal 9.
- [#3128371](https://www.drupal.org/project/backup_migrate/issues/3128371) by solideogloria: info.yml "dependencies" spelled wrong.
- [#3130772](https://www.drupal.org/project/backup_migrate/issues/3130772) by DamienMcKenna: Remove dependencies from info.yml file.
- [#3131711](https://www.drupal.org/project/backup_migrate/issues/3131711) by douggreen: 'label' key defined twice for `backup_migrate.profile.*` config_entity schema in `backup_migrate.schema.yml`.

## Backup and Migrate 8.x-4.1, 2019-12-20

- [#2975065](https://www.drupal.org/project/backup_migrate/issues/2975065) by Dinu Rodnitchi, Alex Andrascu: Add encryption support.
- [#3041404](https://www.drupal.org/project/backup_migrate/issues/3041404) by DamienMcKenna: Add CHANGELOG.txt.
- [#2947276](https://www.drupal.org/project/backup_migrate/issues/2947276) by liliancatanoi90, rajeshwari10, minakshiPh, Elaman, emartoni, DamienMcKenna, alonaoneill: Missing module help.
- [#2947219](https://www.drupal.org/project/backup_migrate/issues/2947219) by bryrock, PhilY, Alex Andrascu: Timestamp no longer uses site timezone.
- [#2962548](https://www.drupal.org/project/backup_migrate/issues/2962548) by mattshoaf, Alex Andrascu: Depends on Core Entity module.
- [#2992448](https://www.drupal.org/project/backup_migrate/issues/2992448) by Pasqualle, riddhi.addweb: Warning after install.
- [#2935402](https://www.drupal.org/project/backup_migrate/issues/2935402) by DamienMcKenna, ikit-claw: Further improve coding standards compliance.
- [#3028984](https://www.drupal.org/project/backup_migrate/issues/3028984) by i-trokhanenko, Roman Dyn: Replace deprecated `REQUEST_TIME`.
- [#2925371](https://www.drupal.org/project/backup_migrate/issues/2925371) by tibezh: Usable menu items in Administration menu.
- [#2950887](https://www.drupal.org/project/backup_migrate/issues/2950887) by liliancatanoi90: Admin menu item is missing a description.
- [#3013182](https://www.drupal.org/project/backup_migrate/issues/3013182) by RoshniPatel.addweb: 'label' key defined twice for `backup_migrate.profile.*` config_entity schema in `backup_migrate.schema.yml`.
- [#3047223](https://www.drupal.org/project/backup_migrate/issues/3047223) by DamienMcKenna: Temporarily remove NodeSquirrel integration.
- [#3047798](https://www.drupal.org/project/backup_migrate/issues/3047798) by trustypelletgun, cosmicdreams: Corrected typo in MySQLi extension error message.
- [#3053127](https://www.drupal.org/project/backup_migrate/issues/3053127) by DamienMcKenna, JonMcL: Added README.md note about the new B&M Flysystem module.
- [#2998626](https://www.drupal.org/project/backup_migrate/issues/2998626) by frob, DamienMcKenna, JonMcL: Bad logic halts functionality if the uncompressed file is too large.
- [#3006542](https://www.drupal.org/project/backup_migrate/issues/3006542) by ashlewis, DamienMcKenna, cl.choong, jacklee0410, maki3000, TedWS: Failed to connect to MySQL server.

## Backup and Migrate 8.x-4.0, 2018-03-29

- [#2937840](https://www.drupal.org/project/backup_migrate/issues/2937840) by hugronaphor: Avoid "Unable to find the wrapper "private"" error.

## Backup and Migrate 8.x-4.0-rc1, 2018-02-21

- [#2945253](https://www.drupal.org/project/backup_migrate/issues/2945253) by liliancatanoi90, mlahde, Dinu Rodnitchi: Restoring wrong type of file gives "Restore Complete."
- [#2931261](https://www.drupal.org/project/backup_migrate/issues/2931261) by Dinu Rodnitchi, yukare, liliancatanoi90: Wrong settings profile in schedule edit.
- [#2939721](https://www.drupal.org/project/backup_migrate/issues/2939721) by gaurav.kapoor: Variable `$out` declared but not being used anywhere.
- [#2930752](https://www.drupal.org/project/backup_migrate/issues/2930752) by DamienMcKenna, Venkatesh Rajan.J: Fix coding standards compliance bugs.
- [#2912460](https://www.drupal.org/project/backup_migrate/issues/2912460) by DamienMcKenna, Dinu Rodnitchi: Removed two unused permissions, fixed backup download access, test improvements; added tests for all basic functionality (D8).
- By Alex Andrascu: Removing tests from lib folder. This will be refactored and reintroduced in a later version.

## Backup and Migrate 8.x-4.0-beta3, 2017-10-12

- [#2913362](https://www.drupal.org/project/backup_migrate/issues/2913362) by Dinu Rodnitchi, fietserwin, zheleong, Alex Andrascu, paolo m.: The destination upload does not exist.

## Backup and Migrate 8.x-4.0-beta2, 2017-10-04

- [#2913362](https://www.drupal.org/project/backup_migrate/issues/2913362) by fietserwin: The destination upload does not exist.
- By Alex Andrascu: Fix for #2913021.

## Backup and Migrate 8.x-4.0-beta1, 2017-09-29

- [#2912153](https://www.drupal.org/project/backup_migrate/issues/2912153) by Alex Andrascu: Roadmap, including MySQL autocommit support for faster restores of larger databases (config options for this tweak still to be added).
- [#2831297](https://www.drupal.org/project/backup_migrate/issues/2831297) by szeidler, Alex Andrascu: Exclude cache table contents by default.
- By Alex Andrascu: Coding standards amends.
- [#2878538](https://www.drupal.org/project/backup_migrate/issues/2878538) by pankajxenix, Alex Andrascu, Eugen Andrasescu: Warning: `ZipArchive::getNameIndex()`: Invalid or uninitialized Zip object in BackupMigrate; fixes filesize for all compressed files.
- [#2135827](https://www.drupal.org/project/backup_migrate/issues/2135827) by Alex Andrascu: The Download method should be related to the role permissions.
- [#2826108](https://www.drupal.org/project/backup_migrate/issues/2826108) by PushaMD: Use tokens to create file names for backups.
- [#2877008](https://www.drupal.org/project/backup_migrate/issues/2877008) by Dinu Rodnitchi: Ability to order the Saved Backups by Name, Date and Size.
- [#2905381](https://www.drupal.org/project/backup_migrate/issues/2905381) by smk-ka: composer.json does not contain valid JSON.
- [#2803371](https://www.drupal.org/project/backup_migrate/issues/2803371) by Alex Andrascu, HongPong, couturier: Uninstall "entire site" config message looks terrifying.
- By Ronan: Remove composer cruft. Move to Symfony autoloader.
- By Ronan: Add limits and more link to backups tab.
- [#2875686](https://www.drupal.org/project/backup_migrate/issues/2875686) by Alex Andrascu: Split Source and Destination into two separate forms.
- [#2862618](https://www.drupal.org/project/backup_migrate/issues/2862618) by couturier: Add field to "Schedule" for number of backups to keep.
- By Ronan: Fix issue with file timestamps not saving.
- [#2723963](https://www.drupal.org/project/backup_migrate/issues/2723963) by jojyja: Removing unused imports in code.
- [#2692931](https://www.drupal.org/project/backup_migrate/issues/2692931) by pashupathi nath gajawada: Removed `urlInfo()` deprecated method from code base.
- [#2869821](https://www.drupal.org/project/backup_migrate/issues/2869821) by harsha012: Fix the coding standards.
- [#2817045](https://www.drupal.org/project/backup_migrate/issues/2817045) by Dinu Rodnitchi: 'The backup file could not be saved to `private://backup_migrate/` because it does not exist.'
- [#2848869](https://www.drupal.org/project/backup_migrate/issues/2848869): Destination not saved with schedule.
- [#2851741](https://www.drupal.org/project/backup_migrate/issues/2851741) by vibrasphere: 'UTF8 is not supported by the MySQL server' - When database port was not set.
- [#2732195](https://www.drupal.org/project/backup_migrate/issues/2732195) by szeidler: Fatal error: Call to a member function `create()` on a non-object in `backup_migrate/vendor/Drupal/backup_migrate/Core/src/Source/MySQLiSource.php` on line 48.
- By Ronan: Put in a check for the mysqli extension.
- [#2865949](https://www.drupal.org/project/backup_migrate/issues/2865949) by Dinu Rodnitchi, pmunch: Add Backup Destination: "Directory Path" field is gone.
- [#2687555](https://www.drupal.org/project/backup_migrate/issues/2687555) by Znak, dhruveshdtripathi: Add a link to configurations page.

## Backup and Migrate 8.x-4.0-alpha2, 2017-04-04

- [#2809965](https://www.drupal.org/project/backup_migrate/issues/2809965) by szeidler: Restore - nothing happens.
- [#2833245](https://www.drupal.org/project/backup_migrate/issues/2833245): Error: Call to undefined method `confSet()` - prevents backup.
- [#2741245](https://www.drupal.org/project/backup_migrate/issues/2741245) by tobiberlin: On restore page: Notice: Undefined index: groups in `Drupal\backup_migrate\Drupal\Config\DrupalConfigHelper::addFieldsFromSchema()`.
- [#2741301](https://www.drupal.org/project/backup_migrate/issues/2741301): Noise on destination page.
- [#2820911](https://www.drupal.org/project/backup_migrate/issues/2820911): Web server detection failing for IIS.
- [#2826107](https://www.drupal.org/project/backup_migrate/issues/2826107) by szeidler: Error messages on settings page.
- [#2741265](https://www.drupal.org/project/backup_migrate/issues/2741265) by szeidler: Backups not gzipped.
- [#2834178](https://www.drupal.org/project/backup_migrate/issues/2834178) by pifagor: Notice: Undefined index: `settings_profile_id`.
- [#2741257](https://www.drupal.org/project/backup_migrate/issues/2741257) by tobiberlin: On schedule page: Strict warning: Declaration of `Drupal\backup_migrate\Controller\ScheduleListBuilder::buildRow()` should be compatible with parent.
- By Ronan: Updated dependencies.
- [#2749885](https://www.drupal.org/project/backup_migrate/issues/2749885) by szeidler: Database irreversibly corrupted after export and restore - encoding issues with non-ASCII text.

## Backup and Migrate 8.x-4.0-alpha1, 2016-05-09

- By Ronan: First release for Drupal 8.