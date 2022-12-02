rm -rf tmp
mkdir -p tmp/packageweight
cp -R classes tmp/packageweight
cp -R config tmp/packageweight
cp -R docs tmp/packageweight
cp -R override tmp/packageweight
cp -R sql tmp/packageweight
cp -R src tmp/packageweight
cp -R translations tmp/packageweight
cp -R views tmp/packageweight
cp -R upgrade tmp/packageweight
cp -R vendor tmp/packageweight
cp -R index.php tmp/packageweight
cp -R logo.png tmp/packageweight
cp -R packageweight.php tmp/packageweight
cp -R config.xml tmp/packageweight
cp -R LICENSE tmp/packageweight
cp -R README.md tmp/packageweight
cd tmp && find . -name ".DS_Store" -delete
zip -r packageweight.zip . -x ".*" -x "__MACOSX"
