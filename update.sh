#!/bin/bash

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