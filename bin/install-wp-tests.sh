#!/usr/bin/env bash
# Installs WordPress and the WordPress PHPUnit test library for running the plugin tests.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
#
# The test suite empties every table in <db-name>, so never point it at a site's database.

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

set -ex

resolve_version() {
	if [ "$WP_VERSION" == "latest" ]; then
		WP_VERSION=$(curl -s https://api.wordpress.org/core/version-check/1.7/ | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
	fi

	# Test library: release branch for x.y, tag for x.y.z, trunk otherwise.
	if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
		WP_TESTS_REF=$WP_VERSION
	elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
		WP_TESTS_REF="$WP_VERSION"
	else
		WP_TESTS_REF="trunk"
	fi
}

install_wp() {
	if [ -d "$WP_CORE_DIR/wp-includes" ]; then
		return
	fi

	mkdir -p "$WP_CORE_DIR"

	if [ "$WP_VERSION" == "nightly" ] || [ "$WP_VERSION" == "trunk" ]; then
		curl -sL https://wordpress.org/nightly-builds/wordpress-latest.zip -o "$TMPDIR/wordpress-nightly.zip"
		unzip -q "$TMPDIR/wordpress-nightly.zip" -d "$TMPDIR/wordpress-nightly/"
		mv "$TMPDIR/wordpress-nightly/wordpress/"* "$WP_CORE_DIR"
	else
		curl -sL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" -o "$TMPDIR/wordpress.tar.gz"
		tar --strip-components=1 -zxm -C "$WP_CORE_DIR" < "$TMPDIR/wordpress.tar.gz"
	fi

	curl -sL https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php -o "$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
	if [ -d "$WP_TESTS_DIR/includes" ]; then
		return
	fi

	# svn isn't available everywhere, so fetch only the test library with a sparse git checkout.
	local checkout="$TMPDIR/wordpress-develop"
	rm -rf "$checkout"
	git -c core.longpaths=true clone --depth 1 --filter=blob:none --sparse --branch "$WP_TESTS_REF" https://github.com/WordPress/wordpress-develop.git "$checkout"
	git -c core.longpaths=true -C "$checkout" sparse-checkout set tests/phpunit/includes tests/phpunit/data

	mkdir -p "$WP_TESTS_DIR"
	cp -R "$checkout/tests/phpunit/includes" "$WP_TESTS_DIR/"
	cp -R "$checkout/tests/phpunit/data" "$WP_TESTS_DIR/"
	curl -sL "https://raw.githubusercontent.com/WordPress/wordpress-develop/${WP_TESTS_REF}/wp-tests-config-sample.php" -o "$WP_TESTS_DIR/wp-tests-config.php"
	rm -rf "$checkout"

	local core_dir
	core_dir=$(echo "$WP_CORE_DIR" | sed -e "s/\/$//")
	sed -i.bak "s|dirname( __FILE__ ) . '/src/'|'$core_dir/'|" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s|__DIR__ . '/src/'|'$core_dir/'|" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i.bak "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
}

install_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return 0
	fi

	local parts=(${DB_HOST//\:/ })
	local host=${parts[0]}
	local port=${parts[1]}
	local extra=""

	if [ -n "$port" ]; then
		extra=" --port=$port --protocol=tcp"
	elif [ -n "$host" ]; then
		extra=" --host=$host --protocol=tcp"
	fi

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$extra || true
}

resolve_version
install_wp
install_test_suite
install_db
