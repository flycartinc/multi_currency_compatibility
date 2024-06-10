echo "Multi currency compatibility"
current_dir="$PWD"
composer_run(){
  # shellcheck disable=sc2164
  cd "$current_dir"
  composer install --no-dev -q
  composer update --no-dev -q
  cd "$current_dir"
}
copy_folder(){
  cd "$current_dir"
  cd ..
  pack_folder=$PWD"/generated_pack"
  compressed_plugin_folder=$pack_folder"/multi_currency_compatibility"
  if [ -d "$pack_folder"]; then
    rm -r "$pack_folder"
  fi
  mkdir "$pack_folder"
  mkdir "$compressed_plugin_folder"
  move_dir=("App" "Assets" "vendor" "wdr-multi-currency-compatibility.php")
  # shellcheck disable=sc2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$compressed_plugin_folder/$dir"
  done
  cd "$current_dir"
}
function zip_folder(){
  cd "$current_dir"
  cd ..
  pack_compress_folder=$PWD"/generated_pack"
  cd "$pack_compress_folder"
  zip_name="wdr-multi-currency-compatibility"
  rm "$zip_name".zip
  zip -r "$zip_name".zip $zip_name -q
  zip -d "$zip_name".zip __MACOSX/\*
  zip -d "$zip_name".zip \*/.DS_Store
}
echo "Composer Run:"
composer_run
echo "Copy Folder:"
copy_folder
echo "Zip Folder:"
zip_folder
echo "End"