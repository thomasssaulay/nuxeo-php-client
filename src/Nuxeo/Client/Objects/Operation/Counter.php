<?php
/**
 * (C) Copyright 2018 Nuxeo SA (http://nuxeo.com/) and contributors.
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
 */

namespace Nuxeo\Client\Objects\Operation;


use JMS\Serializer\Annotation as Serializer;

class Counter {

  /**
   * @var CounterTimestampedValue[]
   * @Serializer\Type("array<Nuxeo\Client\Objects\Operation\CounterTimestampedValue>")
   */
  protected $values;

  /**
   * @var CounterTimestampedValue[]
   * @Serializer\Type("array<Nuxeo\Client\Objects\Operation\CounterTimestampedValue>")
   */
  protected $deltas;

  /**
   * @var CounterTimestampedValue[]
   * @Serializer\Type("array<Nuxeo\Client\Objects\Operation\CounterTimestampedValue>")
   */
  protected $speed;

  /**
   * @return CounterTimestampedValue[]
   */
  public function getValues() {
    return $this->values;
  }

  /**
   * @return CounterTimestampedValue[]
   */
  public function getDeltas() {
    return $this->deltas;
  }

  /**
   * @return CounterTimestampedValue[]
   */
  public function getSpeed() {
    return $this->speed;
  }

}
