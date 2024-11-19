import os
import shutil

def copy_readme_to_index(source_path, dest_path):
    if os.path.exists(source_path):
        os.makedirs(os.path.dirname(dest_path), exist_ok=True)
        shutil.copy(source_path, dest_path)

def build_docs():
    # Get the directory of the current script
    current_dir = os.path.dirname(os.path.abspath(__file__))
    project_root = os.path.dirname(current_dir)
    
    # Change to the docs directory
    os.chdir(current_dir)
    
    # Copy main README.md as index.md
    shutil.copy(os.path.join(project_root, 'README.md'), 'index.md')

    # Walk through the modules directory
    modules_dir = os.path.join(project_root, 'modules')
    for root, dirs, files in os.walk(modules_dir):
        if 'README.md' in files:
            # Construct the source and destination paths
            readme_path = os.path.join(root, 'README.md')
            relative_path = os.path.relpath(root, project_root)
            dest_path = os.path.join(current_dir, relative_path, 'index.md')
            
            # Copy the README to the docs structure
            copy_readme_to_index(readme_path, dest_path)

if __name__ == '__main__':
    build_docs()