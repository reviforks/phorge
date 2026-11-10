<?php

final class PhabricatorFileURITestCase extends PhabricatorTestCase {

  public function testContentURIFilenames() {
    $env = $this->newURIEnvironment();

    $cases = array(
      array('profile', 'image/png', 'profile.png'),
      array('photo', 'image/jpeg', 'photo.jpg'),
      array('photo', 'image/jpg', 'photo.jpg'),
      array('animation', 'image/gif', 'animation.gif'),
      array('photo', 'image/webp', 'photo.webp'),
      array('favicon', 'image/x-ico', 'favicon.ico'),
      array('favicon', 'image/x-icon', 'favicon.ico'),
      array('favicon', 'image/vnd.microsoft.icon', 'favicon.ico'),
      array('vector', 'image/svg+xml', 'vector.svg'),
      array('bitmap', 'image/bmp', 'bitmap.bmp'),
      array('bitmap', 'image/x-ms-bmp', 'bitmap.bmp'),
      array('photo', 'image/avif', 'photo.avif'),
      array('scan', 'image/tiff', 'scan.tiff'),
      array('profile.dat', 'image/png', 'profile.dat.png'),
      array('profile.html', 'image/png', 'profile.html.png'),
      array('profile.png.txt', 'image/png', 'profile.png.txt.png'),
      array('opaque', 'application/octet-stream', 'opaque'),
      array('unknown', 'image/unknown', 'unknown'),
      array('document', 'application/pdf', 'document'),
      array('page', 'text/html', 'page'),
      array('data', 'application/json', 'data'),
      array('unknown', null, 'unknown'),
    );

    foreach ($cases as $case) {
      list($name, $mime_type, $expected) = $case;
      $file = $this->newFile($name, $mime_type);
      $this->assertContentURIs($file, $expected);
      $this->assertEqual($name, $file->getName());
    }
  }

  public function testCacheableExtensionsArePreserved() {
    $env = $this->newURIEnvironment();

    $extensions = array(
      'png', 'PNG', 'jpg', 'JPEG', 'gif', 'webp', 'svg', 'SVGZ', 'ico',
      'bmp', 'avif', 'tif', 'TIFF', 'woff', 'woff2', 'ttf', 'otf', 'eot',
      'pdf', 'css', 'js', 'zip',
    );

    foreach ($extensions as $extension) {
      $name = 'photo.'.$extension;
      $file = $this->newFile($name, 'image/png');
      $this->assertContentURIs($file, $name);
    }
  }

  public function testURIFilenameNormalization() {
    $env = $this->newURIEnvironment();
    $transform = $this->newTransform();

    $cases = array(
      array('my profile/50%#?', 'my_profile_50', 'my_profile_50.png'),
      array('photo .PNG', 'photo_.PNG', 'photo_.PNG'),
      array('', 'file', 'file.png'),
      array('.', 'dot', 'dot.png'),
      array('..', 'dotdot', 'dotdot.png'),
      array('a\\b & c', 'a_b_c', 'a_b_c.png'),
      array("caf\xC3\xA9", "caf\xC3\xA9", 'caf%C3%A9.png'),
    );

    foreach ($cases as $case) {
      list($name, $normalized, $expected) = $case;
      $this->assertEqual(
        $normalized,
        PhabricatorFile::normalizeFileName($name));

      $file = $this->newFile($name, 'image/png');
      $this->assertContentURIs($file, $expected);
      $this->assertEqual(
        $expected,
        basename(id(new PhutilURI($file->getURIForTransform($transform)))
          ->getPath()));
    }
  }

  public function testTransformURIFilenames() {
    $env = $this->newURIEnvironment();
    $transform = $this->newTransform();

    $cases = array(
      array('profile', 'image/png', 'profile.png'),
      array('photo.png', 'image/png', 'photo.png'),
      array('photo.PNG', 'image/png', 'photo.PNG'),
      array('photo', 'image/jpeg', 'photo.jpg'),
      array('animation', 'image/gif', 'animation.gif'),
      array('photo', 'image/webp', 'photo.webp'),
      array('document', 'application/octet-stream', 'document.png'),
    );

    foreach ($cases as $case) {
      list($name, $mime_type, $expected) = $case;
      $file = $this->newFile($name, $mime_type);
      $this->assertEqual(
        'https://phorge.example.com/file/xform/profile/'.$file->getPHID().
        '/'.$file->getSecretKey().'/'.$expected,
        $file->getURIForTransform($transform));
    }
  }

  public function testFileRoutes() {
    $app = new PhabricatorFilesApplication();
    $file = $this->newFile('profile', 'image/png');

    foreach (array($app->getRoutes(), $app->getResourceRoutes()) as $routes) {
      $map = id(new AphrontRoutingMap())
        ->setSite(new PhabricatorResourceSite())
        ->setApplication($app)
        ->setRoutes($routes);

      foreach (array('', '@test/') as $instance) {
        $paths = array(
          '/file/xform/'.$instance.'profile/'.$file->getPHID().
            '/'.$file->getSecretKey().'/'
            => 'PhabricatorFileTransformController',
          '/file/data/'.$instance.$file->getSecretKey().
            '/'.$file->getPHID().'/'
            => 'PhabricatorFileDataController',
          '/file/download/'.$instance.$file->getSecretKey().
            '/'.$file->getPHID().'/'
            => 'PhabricatorFileDataController',
        );

        foreach ($paths as $path => $controller) {
          $original = $map->routePath($path);
          $this->assertTrue($original !== null, $path);

          $names = array('', 'profile', 'profile.png', 'unrelated.jpg');
          foreach ($names as $name) {
            $result = $map->routePath($path.$name);
            $this->assertTrue($result !== null, $path.$name);
            $this->assertEqual(
              $controller,
              get_class($result->getController()));
            $this->assertEqual($original->getURIData(), $result->getURIData());
            $data = $result->getURIData();
            $this->assertEqual($file->getPHID(), $data['phid']);
            $this->assertEqual($file->getSecretKey(), $data['key']);
            $this->assertEqual(trim($instance, '@/'), $data['instance']);
            if ($controller === 'PhabricatorFileTransformController') {
              $this->assertEqual('profile', $data['transform']);
            }
          }
        }

        $this->assertEqual(
          null,
          $map->routePath('/file/xform/'.$instance.'profile/'));
      }
    }
  }

  public function testURIHostsAndInstances() {
    $env = $this->newURIEnvironment();
    $file = $this->newFile('profile', 'image/png');
    $transform = $this->newTransform();

    foreach (array(null, 'https://files.example.com') as $alternate) {
      $env->overrideEnvConfig('security.alternate-file-domain', $alternate);
      $host = nonempty($alternate, 'https://phorge.example.com');

      foreach (array(null, 'test') as $instance) {
        $env->overrideEnvConfig('cluster.instance', $instance);
        $prefix = ($instance === null) ? '' : '@test/';
        $data_path = '/file/data/'.$prefix.$file->getSecretKey().
          '/'.$file->getPHID().'/profile.png';
        $download_path = str_replace('/data/', '/download/', $data_path);

        $file->setIsPartial(0);
        $this->assertEqual($host.$data_path, $file->getViewURI());
        $this->assertEqual($host.$download_path, $file->getDownloadURI());
        $this->assertEqual($file->getViewURI(), $file->getBestURI());
        $this->assertEqual(
          $host.'/file/xform/'.$prefix.'profile/'.$file->getPHID().
          '/'.$file->getSecretKey().'/profile.png',
          $file->getURIForTransform($transform));

        $file->setIsPartial(1);
        $this->assertEqual(
          'https://phorge.example.com'.$data_path,
          $file->getViewURI());
        $this->assertEqual(
          'https://phorge.example.com'.$download_path,
          $file->getDownloadURI());
      }
    }

    $this->assertEqual('/F123', $file->getInfoURI());
    $this->assertEqual('/F123', $file->getURI());
    $file->setMimeType('application/octet-stream');
    $this->assertEqual('/F123', $file->getBestURI());
  }

  public function testLegacyProfileImageCacheInvalidation() {
    $env = $this->newURIEnvironment();
    $file = $this->newFile('profile', 'image/png');
    $cache = new PhabricatorUserProfileImageCacheType();
    $key = PhabricatorUserProfileImageCacheType::KEY_URI;
    $legacy_uri = PhabricatorEnv::getCDNURI(
      '/file/data/'.$file->getSecretKey().'/'.$file->getPHID().'/profile');

    foreach (array(null, $file->getPHID()) as $image_phid) {
      $user = id(new PhabricatorUser())
        ->setProfileImagePHID($image_phid);

      // Reproduce a cache row from before URI filenames gained extensions,
      // with the CDN, instance, and profile image PHID all unchanged.
      $legacy_parts = array(
        PhabricatorEnv::getCDNURI('/'),
        PhabricatorEnv::getEnvConfig('cluster.instance'),
        $image_phid,
      );
      $legacy_version = PhabricatorHash::digestForIndex(
        serialize($legacy_parts));

      $this->assertFalse(
        $cache->isRawCacheDataValid(
          $user,
          $key,
          $legacy_version.','.$legacy_uri));
    }
  }

  private function assertContentURIs(PhabricatorFile $file, $name) {
    $suffix = $file->getSecretKey().'/'.$file->getPHID().'/'.$name;
    $base = 'https://phorge.example.com/file/';
    $this->assertEqual($base.'data/'.$suffix, $file->getViewURI());
    $this->assertEqual($base.'download/'.$suffix, $file->getDownloadURI());
    $this->assertEqual($base.'data/'.$suffix, $file->getCDNURI('data'));
    $this->assertEqual($base.'download/'.$suffix, $file->getCDNURI('download'));
  }

  private function newFile($name, $mime_type) {
    // URI generation only needs an in-memory file, without storage fixtures.
    return id(new PhabricatorFile())
      ->setID(123)
      ->setPHID('PHID-FILE-abcdefghijklmnopqrst')
      ->setSecretKey('abcdefghijklmnopqrst')
      ->setName($name)
      ->setMimeType($mime_type);
  }

  private function newTransform() {
    return id(new PhabricatorFileThumbnailTransform())
      ->setKey(PhabricatorFileThumbnailTransform::TRANSFORM_PROFILE);
  }

  private function newURIEnvironment() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig(
      'phabricator.base-uri',
      'https://phorge.example.com');
    $env->overrideEnvConfig('security.alternate-file-domain', null);
    $env->overrideEnvConfig('cluster.instance', null);
    return $env;
  }

}
