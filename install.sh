#!/bin/bash

if [[ "$OSTYPE" == "darwin"* ]]; then
  # install homebrew if missing
  if ! command -v brew &> /dev/null
  then
    /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
  fi

  brew install -q php@8.2 composer p7zip imagemagick pkg-config jpegoptim optipng pngquant wget qt@5
  pecl install imagick
fi

if [[ $OSTYPE == linux* ]]; then
  if ! test -f "/etc/os-release"; then
    echo "Cannot automatically install on this Linux variant, please manually install dependencies"
  fi

  source /etc/os-release

  if [[ "$ID" = 'archarm' ]]; then
    pacman -S --needed php imagemagick php-imagick bzip2 wget jpegoptim optipng pngquant p7zip unzip qt5-base qt5-tools qt5ct composer base-devel
    sed -i.bak 's/^;extension=intl/extension=intl/' /etc/php/php.ini
    sed -i 's/^;extension=bcmath$/extension=bcmath/' /etc/php/php.ini
    sed -i 's/^;extension=bz2/extension=bz2/' /etc/php/php.ini
    sed -i 's/^;extension=gettext/extension=gettext/' /etc/php/php.ini
    sed -i 's/^;extension=exif/extension=exif/' /etc/php/php.ini
    sed -i 's/^;extension=iconv/extension=iconv/' /etc/php/php.ini

    sed -i 's/^; extension = imagick/extension=imagick/' /etc/php/conf.d/imagick.ini
    sed -i 's/^;extension=imagick/extension=imagick/' /etc/php/conf.d/imagick.ini

  elif [[ "$ID" == @(rhel|centos|fedora|almalinux|rocky) ]]; then

    sudo dnf update

    MACHINE=$(uname -m)
    MAJOR_VERSION=${VERSION_ID%%.*}
    USEREMI=true
    if [ "$MAJOR_VERSION" = '8' ]; then
      sudo dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-8.noarch.rpm
      sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
    elif [ "$MAJOR_VERSION" = '9' ]; then
      sudo dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm
      sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
    elif [ "$MAJOR_VERSION" = '38' ]; then
      if [ "$MACHINE" != "aarch64" ]; then
          #"Remi not supported for aarch64 below Fedora 39 "
          sudo dnf install -y https://rpms.remirepo.net/fedora/remi-release-38.rpm
      else
        USEREMI=false
      fi
    elif [ "$MAJOR_VERSION" = '39' ]; then
      sudo dnf install -y https://rpms.remirepo.net/fedora/remi-release-39.rpm
    elif [ "$MAJOR_VERSION" = '40' ]; then
      sudo dnf install -y https://rpms.remirepo.net/fedora/remi-release-40.rpm
    else
      echo "CANNOT AUTO INSTALL ON THIS LINUX VERSION. PLEASE INSTALL MANUALLY"
      echo "ID: $ID"
      echo "MAJOR VERSION: $MAJOR_VERSION"
      exit
    fi

    if [ "$USEREMI" = true ]; then
      sudo dnf module enable php:remi-8.2
    fi

    sudo dnf install -y ImageMagick php php-cli php-common php-json php-zip php-bz2 php-curl php-mbstring php-intl wget unzip p7zip p7zip-plugins jpegoptim optipng pngquant qtchooser qt5-qtbase* qt-devel qt5-qttools-devel composer

    if [ "$USEREMI" = true ]; then
      sudo dnf install -y php-pecl-imagick-im7
    fi
    if [ "$USEREMI" = false ]; then
      sudo dnf install -y php-pecl-imagick
    fi

    # if no qmake then try to use an alternative (required on Fedora 38 at least)
    if ! command -v qmake &> /dev/null; then
      if ! command -v qmake-qt5 &> /dev/null; then
          echo "Cannot resolve qmake, installation aborted"
      fi
      echo "ALIASING QMAKE"
      #qmake="qmake-qt5"
      qmake() {
          qmake-qt5 "$@"
      }
      export -f qmake
    fi

  elif [[ "$ID" = 'ubuntu' ]]; then

      sudo dpkg -l | grep php | tee packages.txt
      sudo add-apt-repository ppa:ondrej/php
      sudo apt update
      sudo apt install build-essential imagemagick p7zip-full php8.2 php8.2-cli curl php8.2-{bz2,curl,mbstring,intl,zip,imagick,xml,dom,simplexml} jpegoptim optipng pngquant qtbase5-dev qtchooser qt5-qmake qtbase5-dev-tools composer
      sudo update-alternatives --set php /usr/bin/php8.2
      # php-pecl-imagick wget unzip p7zip p7zip-plugins

  elif [[ "$ID" = 'debian' ]]; then

      sudo dpkg -l | grep php | tee packages.txt

      sudo apt install apt-transport-https lsb-release ca-certificates wget -y
      sudo wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
      sudo sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
      sudo apt update

      sudo apt install build-essential imagemagick p7zip-full php8.2 php8.2-cli curl php8.2-{bz2,curl,mbstring,intl,zip,imagick,xml,dom,simplexml} jpegoptim optipng pngquant qtbase5-dev qtchooser qt5-qmake qtbase5-dev-tools composer
      sudo update-alternatives --set php /usr/bin/php8.2

  fi
fi

# attempt manual install of composer
if ! command -v composer &> /dev/null; then
  wget https://getcomposer.org/installer -O composer-installer.php
  sudo php composer-installer.php --install-dir=/usr/local/bin --filename=composer
fi

# Install SS - non default install paths not supported...
SSLATEST=$(wget -q -O - "https://api.github.com/repos/Gemba/skyscraper/releases/latest" | grep '"tag_name":' | sed -n 's/.*"tag_name":[[:space:]]*"\([^"]*\)".*/\1/p')
SSINSTALLED=false
if command -v Skyscraper &> /dev/null
then
    SSINSTALLED=true
fi

if $SSINSTALLED && Skyscraper --version | grep -q "$SSLATEST"; then
    echo "Skyscraper present at version $SSLATEST, nothing to do"
else
    # if installed then update if script is there
    if ! test -f "$HOME/skyscraper/update_skyscraper.sh"; then
        cd "$HOME" || exit
        mkdir -p skyscraper
        cd skyscraper || exit
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Gemba/skyscraper/master/update_skyscraper.sh)"
        # wget -q -O - https://raw.githubusercontent.com/Gemba/skyscraper/master/update_skyscraper.sh | bash
      else
        echo 'running update_skyscraper.sh'
        cd "$HOME"/skyscraper || exit
        /bin/bash "$HOME/skyscraper/update_skyscraper.sh"
    fi

    # copy to /usr/bin
    if ! test -f "/usr/bin/Skyscraper" && ! test -f "/usr/local/bin/Skyscraper"; then
      cp Skyscraper /usr/local/bin
    fi

fi

# change back to script location to install BB
cd "$( dirname "${BASH_SOURCE[0]}")" || exit
# this is a bit of a mess and needs reworked
mkdir -p boxart-buddy
cd boxart-buddy || exit

# download latest release
LATEST=$(wget -q -O - "https://api.github.com/repos/boxart-buddy/boxart-buddy/releases/latest" | grep '"tag_name":' | sed -n 's/.*"tag_name":[[:space:]]*"\([^"]*\)".*/\1/p')
[[ -z "$LATEST" ]] && printf '%s\n' "--- Remote server unreachable. Check internet connectivity. Exiting. ---" && exit 1

handle_error() {
    local EXITCODE=$?
    local ACTION=$1
    rm -f VERSION VERSION.txt
    printf '%s\n' "--- Failed to $ACTION Boxart Buddy v${LATEST}, exiting with code $EXITCODE ---"
    exit $EXITCODE
}

source VERSION 2>/dev/null || VERSION=""
if [ "$LATEST" == "$VERSION" ]; then

    printf '\n%s\n' "--- already the latest version, exiting ---"
    printf '%s\n' "Hint: You can force a reinstall by removing the VERSION file by"
    printf '%s\n' "running 'rm VERSION'. Then run $0 again."

else
  cd "$( dirname "${BASH_SOURCE[0]}")" || exit

  echo "--- Fetching Boxart Buddy v$LATEST ---"
  tarball="${LATEST}.tar.gz"
  wget -nv https://github.com/boxart-buddy/boxart-buddy/archive/"$tarball" || handle_error "fetch"

  echo "--- Unpacking ---"
  tar_bin='tar'
  if [[ "$OSTYPE" == "darwin"* ]] ; then
    tar_bin='gtar'
  fi

  $tar_bin xzf "$tarball" --strip-components 1 --overwrite || handle_error "unpack"
  rm -f "$tarball"

  echo "--- Boxart Buddy has been updated to v$LATEST ---"
fi

# composer install
composer install

# run console command to check everything is working
make bootstrap-stage-1
make validate-install
