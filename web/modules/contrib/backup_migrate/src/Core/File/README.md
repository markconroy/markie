# Backup files

Backup files are objects that represent a single backup. They are created
during a backup operation and restored during a restore operation. They do not
need to represent an actual file on the local disk as long as they implement
one or more of the following interfaces:

    \Drupal\backup_migrate\Core\File\BackupFileInterface
    \Drupal\backup_migrate\Core\File\BackupFileReadableInterface
    \Drupal\backup_migrate\Core\File\BackupFileWritableInterface

The latter two interfaces extend `BackupFileInterface`.

A single backup file may be represented by more than one object during the
lifecycle of an operation. It may transition from one object to another
depending on the needs of the plugins operating on the file.

Files should be considered immutable after they are written. A plugin that
wishes to alter a file should create a new file object and copy the data from
the old file to the new one. This preserves the chain of plugin calls. See
[Plugins][plugins]
for details.

## BackupFileInterface

This is the most basic representation of a backup file. An object implementing
only this interface contains metadata about the backup. The data contained in
the file cannot be read from this object, nor can it be written to it.

This type is lightweight and may be returned by a destination in response to a
`listFiles()` or `getFile()` call. It allows remote destinations, such as FTP
or Amazon S3, to return file metadata without loading the file contents until
they are requested. It is also the type returned by `getFile()` on a
destination.

## BackupFileReadableInterface

This subclass of `BackupFileInterface` can also be read from. It allows the
file contents to be used to restore a source.

To turn a `BackupFileInterface` object into a readable file, call
`loadFileForReading()` on the destination that returned the original file
object:

    $destination = new DirectoryDestination(['directory' => '~/mybackups']);
    $file = $destination->getFile('database.mysql');

    // This object has metadata, but the contents cannot necessarily be read.
    if ($file && $file->getMeta('filesize') > 1000) {

      // To read the file, allow the destination to load it for us if needed.
      $file = $destination->loadFileForReading($file);

      // The file contents should now be available.
      if ($file) {
        echo $file->readAll();
      }
    }

## BackupFileWritableInterface

This subclass can be read from and written to. Writable files in Backup and
Migrate are always temporary files and must be created by the temporary file
manager.

Source plugins create an empty temporary file to write the backup to. File
filter plugins, such as compression or encryption filters, create a new
temporary file and copy the contents from the input file to the output file.

The file that results at the end of the plugin chain is used to restore to the
source during a restore operation, or sent to a destination during a backup
operation. Because plugins are responsible for creating new temporary writable
files as needed, they should never require a writable file as input or promise
one as a return value.

## The temporary file manager

All writable files must be created by the temporary file manager. This class
can create a new blank file with a given file extension.

The standard flow of file filters is a chain. One filter hands a file to the
next filter, which copies the data to a new file and hands it on. For example,
the MySQL source generates a database dump file. That file is handed to an
encryption filter, which copies the metadata to a new file containing the
encrypted data. The encrypted file is then passed to a compression filter,
which creates a compressed version of the file. The compressed file is finally
handed to a destination for saving.

At each step, a new file is created with a new extension appended to the end:

    file.mysql -> file.mysql.aes -> file.mysql.aes.gz

To support this flow, the temporary file manager copies file metadata and
provisions a new temporary file with the new file extension. A compressor
plugin might do something like this:

    function afterBackup($file_in) {
      // Get a new file with '.gz' added to the end of the filename.
      $file_out = $this->getTempFileManager()->pushExt($file_in, 'gz');

      if ($this->doCompress($file_in, $file_out)) {
        return $file_out;
      }

      // Compression failed. Return the original file.
      return $file_in;
    }

Similarly, `$this->getTempFileManager()->popExt()` pulls the last file
extension and returns a blank file for decompression before import.

See [Plugins][plugins]
for details on how to make the temporary file manager accessible within a
plugin.

## The temporary file adapter

While the file manager handles temporary file metadata, it cannot provision
actual on-disk files to write to. That operation is different depending on
where the code runs and is therefore the responsibility of the
[Environment][environment]
object.

The environment provides a service called the temporary file adapter, an object
whose class implements
`\Drupal\backup_migrate\Core\Services\TempFileAdapterInterface`. This class
provisions actual temporary files in the host operating system that can be
written to and read from.

The service is also responsible for tracking all files created during an
operation and deleting those files when the operation completes. Backup and
Migrate core comes with a basic adapter that accepts any writable directory as
an argument and creates new temporary files within that directory. This
implementation should suffice for most consuming software, but can be replaced
with another adapter if needed.

See: [Environment][environment]

[environment]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Environment
[plugins]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Plugin
