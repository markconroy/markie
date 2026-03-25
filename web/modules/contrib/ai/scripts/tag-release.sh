#!/bin/sh

GREEN='\033[0;32m'
WHITE_ON_BLUE='\033[104;37m'
NC='\033[0m'

SCRIPT_DIR=`pwd`
WORK_DIR=`mktemp -d`

read -p "Which branch to use: " BRANCH

read -p "Tag to create release for: " TAG
case $TAG in
  "0."*) continue ;;
  *)
    read -p "☢️ Are you sure you want to use $TAG? Then write it again: " RETAG
    case $RETAG in
      $TAG) continue;;
      *) echo "⛔ Nope."; exit 2;;
    esac
esac

echo "${WHITE_ON_BLUE}[0/5] 💁‍♂️ Opening working directory $WORK_DIR …${NC}"
open $WORK_DIR

echo "${WHITE_ON_BLUE}[1/5] Cloning Drupal AI into working directory …${NC}"
cd $WORK_DIR
git clone -q git@git.drupal.org:project/ai.git
cd ai
git checkout $BRANCH

echo "${WHITE_ON_BLUE}[2/5] Building UI …${NC}"
pushd modules/ai_ckeditor
node --version
if [[ $(node --version) != v20.* ]]; then
  echo "This script requires NodeJS v20."
  exit 1
fi
npm version $TAG --allow-same-version --no-git-tag-version
npm install
npm run build
popd

pushd ui/mdxeditor
node --version
if [[ $(node --version) != v20.* ]]; then
  echo "This script requires NodeJS v20."
  exit 1
fi
npm version $TAG --allow-same-version --no-git-tag-version
npm install
npm run build
popd

pushd ui/json-schema-editor
node --version
if [[ $(node --version) != v20.* ]]; then
  echo "This script requires NodeJS v20."
  exit 1
fi
npm version $TAG --allow-same-version --no-git-tag-version
npm install
npm run build
popd

echo "${WHITE_ON_BLUE}[3/5] Committing built UI …${NC}"
# TRICKY: `-f` to force it even if it's listed in .gitignore.
git add -f modules/ai_ckeditor/package.json modules/ai_ckeditor/package-lock.json modules/ai_ckeditor/js/build ui/mdxeditor/package.json ui/mdxeditor/package-lock.json ui/mdxeditor/dist ui/json-schema-editor/dist
# Similar to core: https://git.drupalcode.org/project/drupal/-/commit/b33c9280991c437a3fa05dec941c54bca0ddb7d8
git commit -q -m "Drupal AI $TAG"
git tag "$TAG" HEAD
echo "  ℹ️  ${GREEN}$TAG tag created locally.${NC}"

echo "${WHITE_ON_BLUE}[4/5] Removing built UI …${NC}"
pushd modules/ai_ckeditor
npm version "0.0.0" --allow-same-version --no-git-tag-version
popd
pushd ui/mdxeditor
npm version "0.0.0" --allow-same-version --no-git-tag-version
popd
pushd ui/json-schema-editor
npm version "0.0.0" --allow-same-version --no-git-tag-version
popd
git add -f modules/ai_ckeditor/package.json modules/ai_ckeditor/package-lock.json ui/mdxeditor/package.json ui/mdxeditor/package-lock.json ui/json-schema-editor/package.json ui/json-schema-editor/package-lock.json
git rm -rfq modules/ai_ckeditor/js/build ui/mdxeditor/dist ui/json-schema-editor/dist
# Similar to core: https://git.drupalcode.org/project/drupal/-/commit/f30549fbdd5ebfb2b338c3bbcfda36ac0bf1ca9d
git commit -q -m "Back to dev."
echo "  ℹ️  ${GREEN}Built UI removed locally.${NC}"

echo "${WHITE_ON_BLUE}[5/5] ⚠️  Please verify the 2 new commits and tag at ${GREEN}$WORK_DIR/ai${WHITE_ON_BLUE} …${NC}"
read -p "Are you sure you want to push these 2 commit and tag? <y/N> " prompt
if [[ $prompt == "y" ]]
then
  git push -q && git push -q --tags
  echo "  ℹ️  ${GREEN}$TAG tag pushed to drupal.org.${NC}"
else
  echo "Okay, aborted."
  exit 0
fi
