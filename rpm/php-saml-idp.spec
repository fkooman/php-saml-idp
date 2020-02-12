%global git ca1acc706d96577437a773dd16969ddf15c31fa2

Name:       php-saml-idp
Version:    0.0.0
Release:    0.99%{?dist}
Summary:    SAML IdP

Group:      Applications/Internet
License:    ASL2.0

URL:        https://software.tuxed.net/php-saml-idp
%if %{defined git}
Source0:    https://git.tuxed.net/fkooman/php-saml-idp/snapshot/php-saml-idp-%{git}.tar.xz
%else
Source0:    https://software.tuxed.net/php-saml-idp/files/php-saml-idp-%{version}.tar.xz
Source1:    https://software.tuxed.net/php-saml-idp/files/php-saml-idp-%{version}.tar.xz.asc
Source2:    gpgkey-6237BAF1418A907DAA98EAA79C5EDD645A571EB2
%endif
Source3:    %{name}-httpd.conf
Patch0:     %{name}-autoload.patch

BuildArch:  noarch

BuildRequires:  gnupg2
BuildRequires:  php-fedora-autoloader-devel
BuildRequires:  %{_bindir}/phpab
#    "require": {
#        "ext-date": "*",
#        "ext-dom": "*",
#        "ext-hash": "*",
#        "ext-libxml": "*",
#        "ext-openssl": "*",
#        "ext-spl": "*",
#        "fkooman/otp-verifier": "^0.3",
#        "ext-zlib": "*",
#        "fkooman/secookie": "^4",
#        "ircmaxell/password-compat": "^1.0",
#        "paragonie/constant_time_encoding": "^1|^2",
#        "paragonie/random_compat": ">=1",
#        "php": ">=5.4",
#        "symfony/polyfill-php56": "^1"
#    }
BuildRequires:  php(language) >= 5.4.0
BuildRequires:  php-date
BuildRequires:  php-dom
BuildRequires:  php-hash
BuildRequires:  php-libxml
BuildRequires:  php-openssl
BuildRequires:  php-spl
BuildRequires:  php-composer(fkooman/otp-verifier) >= 0.3
BuildRequires:  php-composer(fkooman/otp-verifier) < 0.4
BuildRequires:  php-zlib
BuildRequires:  php-composer(fkooman/secookie) >= 4
BuildRequires:  php-composer(fkooman/secookie) < 5
BuildRequires:  php-composer(paragonie/constant_time_encoding)
%if 0%{?fedora} < 28 && 0%{?rhel} < 8
BuildRequires:  php-composer(paragonie/random_compat)
BuildRequires:  php-composer(ircmaxell/password-compat)
BuildRequires:  php-composer(symfony/polyfill-php56)
%endif

%if 0%{?fedora} >= 24
Requires:   httpd-filesystem
%else
# EL7 does not have httpd-filesystem
Requires:   httpd
%endif
#    "require": {
#        "ext-date": "*",
#        "ext-dom": "*",
#        "ext-hash": "*",
#        "ext-libxml": "*",
#        "ext-openssl": "*",
#        "ext-spl": "*",
#        "fkooman/otp-verifier": "^0.3",
#        "ext-zlib": "*",
#        "fkooman/secookie": "^4",
#        "ircmaxell/password-compat": "^1.0",
#        "paragonie/constant_time_encoding": "^1|^2",
#        "paragonie/random_compat": ">=1",
#        "php": ">=5.4",
#        "symfony/polyfill-php56": "^1"
#    }
Requires:   php(language) >= 5.4.0
Requires:   php-cli
Requires:   php-date
Requires:   php-dom
Requires:   php-hash
Requires:   php-libxml
Requires:   php-openssl
Requires:   php-spl
Requires:   php-composer(fkooman/otp-verifier) >= 0.3
Requires:   php-composer(fkooman/otp-verifier) < 0.4
Requires:   php-zlib
Requires:   php-composer(fkooman/secookie) >= 4
Requires:   php-composer(fkooman/secookie) < 5
Requires:   php-composer(paragonie/constant_time_encoding)
%if 0%{?fedora} < 28 && 0%{?rhel} < 8
Requires:   php-composer(paragonie/random_compat)
Requires:   php-composer(ircmaxell/password-compat)
Requires:   php-composer(symfony/polyfill-php56)
%endif

%description
SAML IdP written in PHP.

%prep
%if %{defined git}
%setup -qn php-saml-idp-%{git}
%else
gpgv2 --keyring %{SOURCE2} %{SOURCE1} %{SOURCE0}
%setup -qn php-saml-idp-%{version}
%endif
%patch0 -p1

%build
%{_bindir}/phpab -t fedora -o src/autoload.php src
cat <<'AUTOLOAD' | tee -a src/autoload.php
require_once '%{_datadir}/php/fkooman/SeCookie/autoload.php';
require_once '%{_datadir}/php/ParagonIE/ConstantTime/autoload.php';
require_once '%{_datadir}/php/fkooman/Otp/autoload.php';
AUTOLOAD
%if 0%{?fedora} < 28 && 0%{?rhel} < 8
cat <<'AUTOLOAD' | tee -a src/autoload.php
require_once '%{_datadir}/php/random_compat/autoload.php';
require_once '%{_datadir}/php/password_compat/password.php';
require_once '%{_datadir}/php/Symfony/Polyfill/autoload.php';
AUTOLOAD
%endif

%install
mkdir -p %{buildroot}%{_datadir}/%{name}
mkdir -p %{buildroot}%{_datadir}/php/fkooman/SAML/IdP
install -m 0755 -D -p bin/generate-salt.php %{buildroot}%{_bindir}/php-saml-idp-generate-salt
install -m 0755 -D -p bin/add-otp.php %{buildroot}%{_bindir}/php-saml-idp-add-otp
install -m 0755 -D -p bin/init.php %{buildroot}%{_bindir}/php-saml-idp-init
install -m 0755 -D -p bin/add-user.php %{buildroot}%{_bindir}/php-saml-idp-add-user
cp -pr src/* %{buildroot}%{_datadir}/php/fkooman/SAML/IdP
cp -pr locale web %{buildroot}%{_datadir}/%{name}

mkdir -p %{buildroot}%{_sysconfdir}/%{name}
cp -pr config/config.php.example %{buildroot}%{_sysconfdir}/%{name}/config.php
cp -pr config/metadata.php.example %{buildroot}%{_sysconfdir}/%{name}/metadata.php
ln -s ../../../etc/%{name} %{buildroot}%{_datadir}/%{name}/config

mkdir -p %{buildroot}%{_localstatedir}/lib/php-saml-idp
ln -s ../../../var/lib/php-saml-idp %{buildroot}%{_datadir}/php-saml-idp/data

install -m 0644 -D -p %{SOURCE3} %{buildroot}%{_sysconfdir}/httpd/conf.d/%{name}.conf

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/php-saml-idp(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/php-saml-idp || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/php-saml-idp(/.*)?' 2>/dev/null || :
fi

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/%{name}.conf
%dir %attr(0750,root,apache) %{_sysconfdir}/%{name}
%config(noreplace) %{_sysconfdir}/%{name}/config.php
%config(noreplace) %{_sysconfdir}/%{name}/metadata.php
%{_bindir}/*
%dir %{_datadir}/php/fkooman
%dir %{_datadir}/php/fkooman/SAML
%{_datadir}/php/fkooman/SAML/IdP
%{_datadir}/%{name}
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/php-saml-idp
%doc README.md CHANGES.md composer.json config/config.php.example config/metadata.php.example
%license LICENSE

%changelog
* Wed Feb 12 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.99
- rebuilt

* Wed Feb 12 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.98
- rebuilt

* Wed Feb 12 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.97
- rebuilt

* Mon Feb 03 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.96
- rebuilt

* Fri Jan 31 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.95
- rebuilt

* Fri Jan 31 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.94
- rebuilt

* Mon Jan 27 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.93
- rebuilt

* Thu Jan 23 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.92
- rebuilt

* Tue Jan 21 2020 François Kooman <fkooman@tuxed.net> - 0.0.0-0.91
- rebuilt

* Wed Sep 11 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.90
- rebuilt

* Tue Sep 10 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.89
- rebuilt

* Thu Sep 05 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.88
- rebuilt

* Fri Aug 30 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.87
- rebuilt

* Thu Aug 29 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.86
- rebuilt

* Thu Aug 29 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.85
- rebuilt

* Wed Aug 28 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.84
- rebuilt

* Wed Aug 28 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.83
- rebuilt

* Wed Aug 28 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.82
- rebuilt

* Mon Aug 12 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.81
- rebuilt

* Mon Aug 12 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.80
- rebuilt

* Mon Aug 12 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.79
- rebuilt

* Mon Aug 12 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.78
- rebuilt

* Sun Aug 11 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.77
- rebuilt

* Sun Aug 11 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.76
- rebuilt

* Wed Jul 31 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.75
- rebuilt

* Tue Jul 30 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.74
- rebuilt

* Tue Jul 30 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.73
- rebuilt

* Wed Jul 03 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.72
- rebuilt

* Tue Apr 16 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.71
- rebuilt

* Tue Apr 16 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.70
- rebuilt

* Tue Apr 16 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.69
- rebuilt

* Tue Apr 16 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.68
- rebuilt

* Tue Apr 16 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.67
- rebuilt

* Tue Apr 16 2019 François Kooman <fkooman@tuxed.net> - 0.0.0-0.66
- rebuilt
