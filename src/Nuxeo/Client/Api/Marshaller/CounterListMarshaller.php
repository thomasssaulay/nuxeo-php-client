<?php
/**
 * (C) Copyright 2016 Nuxeo SA (http://nuxeo.com/) and contributors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Nuxeo\Client\Api\Marshaller;


use Nuxeo\Client\Api\Objects\Counter;
use Nuxeo\Client\Api\Objects\CounterList;

class CounterListMarshaller extends AbstractJsonObjectMarshaller {

  protected function getType() {
    return array('name' => 'array', 'params' => array(array('name' => 'string'), array('name' => Counter::className)));
  }

  protected function getClassName() {
    return CounterList::className;
  }

}
