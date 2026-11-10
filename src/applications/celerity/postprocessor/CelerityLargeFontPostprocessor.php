<?php

final class CelerityLargeFontPostprocessor
  extends CelerityPostprocessor {

  public function getPostprocessorKey() {
    return 'fontsizeplusone';
  }

  public function getPostprocessorName() {
    return pht('Use Larger Font Size');
  }

  public function buildVariables() {
    $default_variables = $this->getDefault()->getVariables();

    return array(

      'basefont' => '14px '.$default_variables['fontfamily'],

      // Font Sizes
      'biggestfontsize' => '16px',
      'biggerfontsize' => '15px',
      'normalfontsize' => '14px',
      'smallerfontsize' => '13px',
      'smallestfontsize' => '12px',

    );
  }

}
