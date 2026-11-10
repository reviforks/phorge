<?php

final class CelerityResourceTransformerTestCase extends PhabricatorTestCase {

  public function testTransformation() {
    $files = dirname(__FILE__).'/transformer/';
    foreach (Filesystem::listDirectory($files) as $file) {
      $name = basename($file);
      $data = Filesystem::readFile($files.'/'.$file);
      $parts = preg_split('/^~~~+\n/m', $data);
      $parts = array_merge($parts, array(null));

      list($options, $in, $expect) = $parts;

      $parser = new PhutilSimpleOptions();
      $options = $parser->parse($options) + array(
        'minify' => false,
        'name'   => $name,
      );

      $xformer = new CelerityResourceTransformer();
      $xformer->setRawURIMap(
        array(
          '/rsrc/example.png' => '/res/hash/example.png',
        ));
      $xformer->setMinify($options['minify']);

      $result = $xformer->transformResource($options['name'], $in);

      $this->assertEqual(rtrim($expect), rtrim($result), $file);
    }
  }

  public function testFontPreloadURIs() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig(
      'phabricator.base-uri',
      'https://phorge.example.test/');
    $env->overrideEnvConfig('phabricator.developer-mode', false);

    $map = CelerityResourceMap::getNamedInstance('phabricator');
    $css_name = 'rsrc/css/font/font-plex.css';
    $font_name = 'rsrc/externals/font/plex-sans/IBMPlexSans-Regular.woff2';
    $font_path = $map->getURIForName($font_name);
    $keys = array_merge(
      array(null, 'unknown){}theme'),
      array_keys(CelerityPostprocessor::getAllPostprocessors()));

    $domains = array(null, 'https://files.example.test/');
    $previous_css = null;
    foreach ($domains as $domain) {
      $env->overrideEnvConfig('security.alternate-file-domain', $domain);

      // Match the preload in PhabricatorStandardPageView::getHead().
      $preload_uri = PhabricatorEnv::getCDNURI($font_path);
      foreach ($keys as $key) {
        $xformer = id(new CelerityResourceTransformer())
          ->setPostprocessorKey($key)
          ->setCelerityMap($map);
        $css = $xformer->transformResource(
          $css_name, $map->getResourceDataForName($css_name));

        $response = id(new CelerityStaticResourceResponse())
          ->setPostprocessorKey($key);
        $stylesheet_uri = new PhutilURI($response->getURI($map, $css_name));
        $this->assertEqual(
          $domain ? 'files.example.test' : 'phorge.example.test',
          $stylesheet_uri->getDomain());

        $this->assertEqual(
          1,
          preg_match_all(
            '@url\(([^)]*IBMPlexSans-Regular\.woff2)\)@',
            $css,
            $matches));
        foreach ($matches[1] as $path) {
          $uri = new PhutilURI($path);
          $this->assertEqual('/', substr($path, 0, 1));
          $this->assertEqual('', $uri->getProtocol());
          $this->assertEqual('', $uri->getDomain());
          $this->assertEqual($font_path, $path);

          // A path-absolute CSS URL resolves on the stylesheet's origin.
          $resolved_uri = id(clone $stylesheet_uri)->setPath($path);
          $this->assertEqual($preload_uri, (string)$resolved_uri);
        }

        if ($previous_css !== null) {
          $this->assertEqual(
            $previous_css,
            $css,
            'Font CSS must not depend on the CDN or postprocessor key.');
        }
        $previous_css = $css;
      }
    }
  }

}
