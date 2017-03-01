<?php
/**
 * (C) Copyright 2017 Nuxeo SA (http://nuxeo.com/) and contributors.
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

namespace Nuxeo\Client\Tests;

use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Message\Response;
use JMS\Serializer\Annotation as Serializer;
use Nuxeo\Client\Api\Constants;
use Nuxeo\Client\Api\NuxeoClient;
use Nuxeo\Client\Api\Objects\Audit\LogEntry;
use Nuxeo\Client\Api\Objects\Blob\Blob;
use Nuxeo\Client\Api\Objects\Document;
use Nuxeo\Client\Api\Objects\Documents;
use Nuxeo\Client\Api\Objects\Operation;
use Nuxeo\Client\Tests\Objects\Character;
use Nuxeo\Client\Tests\Objects\MyDocType;

class OperationTest extends NuxeoTestCase {

  public function testListDocuments() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('document-list.json')))
    ));

    /** @var Documents $documents */
    $documents = $client
      ->schemas('*')
      ->automation()
      ->param('query', 'SELECT * FROM Document')
      ->execute(null, 'Document.Query');

    $this->assertInstanceOf(Documents::className, $documents);
    $this->assertEquals(5, $documents->size());

    foreach ($documents->getDocuments() as $document) {
      $this->assertNotEmpty($document->getUid());
      $this->assertNotEmpty($document->getPath());
      $this->assertNotEmpty($document->getType());
      $this->assertNotEmpty($document->getState());
      $this->assertNotEmpty($document->getTitle());
      $this->assertNotEmpty($document->getProperty('dc:created'));
    }

    $domain = $documents->getDocument(0);
    $this->assertNotNull($domain);
    $this->assertEquals('Domain', $domain->getType());
    $this->assertEquals('Domain', $domain->getProperty('dc:title'));
    $this->assertNull($domain->getProperty('dc:nonexistent'));
  }

  public function testMyDocTypeDeserialize() {
    $client = new NuxeoClient($this->server->getUrl());

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('document.json')))
    ));

    /** @var MyDocType $document */
    $document = $client
      ->schemas('*')
      ->automation('Document.Fetch')
      ->param('value', '0fa9d2a0-e69f-452d-87ff-0c5bd3b30d7d')
      ->execute(MyDocType::className);

    $this->assertInstanceOf(MyDocType::className, $document);
    $this->assertEquals($document->getCreatedAt(), $document->getProperty('dc:created'));
  }

  public function testComplexProperty() {
    $client = new NuxeoClient($this->server->getUrl());

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('document.json')))
    ));

    /** @var Document $document */
    $document = $client
      ->schemas('*')
      ->automation('Document.Fetch')
      ->param('value', '0fa9d2a0-e69f-452d-87ff-0c5bd3b30d7d')
      ->execute();

    /** @var Character $doc */
    $doc = $document->getProperty('custom:complex', Character::className);
    $this->assertNotEmpty($doc->name);
  }

  public function testRelatedProperty() {
    $client = new NuxeoClient($this->server->getUrl());

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('document.json'))),
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('document.json')))
    ));

    /** @var Document $document */
    $document = $client
      ->schemas('*')
      ->automation('Document.Fetch')
      ->param('value', '0fa9d2a0-e69f-452d-87ff-0c5bd3b30d7d')
      ->execute();

    /** @var Operation\DocRef $docRef */
    $docRef = $document->getProperty('custom:related', Operation\DocRef::className);
    $this->assertInstanceOf(Operation\DocRef::className, $docRef);
    $this->assertInstanceOf(Document::className, $doc = $docRef->getDocument());
    $this->assertNotEmpty($doc->getPath());
  }

  public function testGetBlob() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);

    $this->server->enqueue(array(
      new Response(200, null, self::MYFILE_CONTENT)
    ));

    /** @var \Nuxeo\Client\Api\Objects\Blob\Blob $blob */
    $blob = $client->automation('Blob.Get')
      ->input(self::MYFILE_DOCPATH)
      ->execute(Blob::className);

    /** @var EntityEnclosingRequestInterface $request */
    list($request) = $this->server->getReceivedRequests(true);

    $this->assertEquals(sprintf('{"params":{},"input":"%s"}', self::MYFILE_DOCPATH), (string) $request->getBody());
    $this->assertStringEqualsFile($blob->getFile()->getPathname(), self::MYFILE_CONTENT);
  }

  /**
   * @expectedException \Nuxeo\Client\Internals\Spi\NuxeoClientException
   */
  public function testCannotLoadBlob() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);

    $this->server->enqueue(array(
      new Response(200, null, null)
    ));

    $client->automation('Blob.Attach')->input(Blob::fromFile('/void', null))->execute(Blob::className);

    $this->assertCount(0, $this->server->getReceivedRequests());
  }

  public function testLoadBlob() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);

    $this->server->enqueue(array(
      new Response(200)
    ));

    $client->automation('Blob.AttachOnDocument')
      ->param('document', self::MYFILE_DOCPATH)
      ->input(Blob::fromFile($this->getResource('user.json'), null))
      ->execute(Blob::className);

    $requests = $this->server->getReceivedRequests(true);

    /** @var EntityEnclosingRequest $request */
    list($request) = $requests;

    $this->assertArrayHasKey('content-type', $request->getHeaders());
    $this->assertStringMatchesFormat(
      'multipart/related;boundary=%s',
      $request->getHeader('content-type')->__toString());

  }

  public function testDirectoryEntries() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('directory-entries.json')))
    ));

    $continents = $client->automation('Directory.Entries')
      ->param('directoryName', 'continent')
      ->execute(Operation\DirectoryEntries::className);

    $this->assertInstanceOf(Operation\DirectoryEntries::className, $continents);
    $this->assertCount(7, $continents);
    $this->server->flush();

    $ids = array('id001', 'id002', 'id003', 'id004');
    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), json_encode($ids))
    ));

    $continents = $client->automation('Directory.CreateEntries')
      ->param('directoryName', 'continent')
      ->param('entries', $client->getConverter()->writeJSON(Operation\DirectoryEntries::fromArray(array(
        array('id' => 'id001', 'label' => 'label.continent.one'),
        array('id' => 'id002', 'label' => 'label.continent.two', 'ordering' => 42),
        array('id' => 'id003', 'label' => 'label.continent.three', 'obsolete' => 1),
        array('id' => 'id004', 'label' => 'label.continent.four', 'ordering' => 666, 'obsolete' => 5),
      ))))
      ->execute();

    $this->assertCount(1, $requests = $this->server->getReceivedRequests(true));

    /** @var EntityEnclosingRequestInterface $request */
    list($request) = $requests;

    $this->assertNotNull($decoded = json_decode((string) $request->getBody(), true));
    $this->assertTrue(!empty($decoded['params']['entries']) && is_string($decoded['params']['entries']));
    $this->assertTrue(null !== ($entries = json_decode($decoded['params']['entries'], true)) && !empty($entries[0]['id']));
    $this->assertEquals('id001', $entries[0]['id']);
    $this->assertEquals('label.continent.one', $entries[0]['label']);
    $this->assertEquals(42, $entries[1]['ordering']);
    $this->assertEquals(5, $entries[3]['obsolete']);
    $this->assertEquals($ids, $continents);
  }

  public function testCounters() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);
    $counterName = 'org.nuxeo.web.sessions';

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('counters.json')))
    ));

    $counters = $client->automation('Counters.GET')
      ->param('counterNames', $counterName)
      ->execute(Operation\CounterList::className);

    $this->assertInstanceOf(Operation\CounterList::className, $counters);
    $this->assertCount(1, $counters);

    $this->assertCount(0, $counters[$counterName]->getSpeed());
    $this->assertCount(1, $counters[$counterName]->getDeltas());
    $this->assertCount(1, $counterValues = $counters[$counterName]->getValues());

    $this->assertNotEmpty($counterValues[0]->getTimestamp());
    $this->assertNotEmpty($counterValues[0]->getValue());
  }

  public function testAuditQuery() {
    $client = new NuxeoClient($this->server->getUrl(), self::LOGIN, self::PASSWORD);

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('audit-query.json')))
    ));

    $entries = $client->automation('Audit.Query')
      ->param('query', 'from LogEntry')
      ->execute(Operation\LogEntries::className);

    $this->assertInstanceOf(Operation\LogEntries::className, $entries);
    $this->assertCount(2, $entries);

    /** @var LogEntry $entry */
    $this->assertInstanceOf(LogEntry::className, $entry = $entries[0]);
    $this->assertNotEmpty($entry->getCategory());
    $this->assertNotEmpty($entry->getDocLifeCycle());
    $this->assertNotEmpty($entry->getDocPath());
    $this->assertNotEmpty($entry->getDocType());
    $this->assertNotEmpty($entry->getDocUUID());
    $this->assertNotEmpty($entry->getEventDate());
    $this->assertNotEmpty($entry->getEventId());
    $this->assertNotEmpty($entry->getPrincipalName());
    $this->assertNotEmpty($entry->getRepositoryId());

    $this->assertInstanceOf(LogEntry::className, $entry = $entries[1]);
    $this->assertNotEmpty($entry->getComment());
  }

  public function testActionsGet() {
    $client = new NuxeoClient($this->server->getUrl());

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('actions-get.json')))
    ));

    $actions = $client->automation('Actions.GET')
      ->param('category', 'VIEW_ACTION_LIST')
      ->input(new Operation\DocRef(self::MYFILE_DOCPATH))
      ->execute(Operation\ActionList::className);

    /** @var EntityEnclosingRequestInterface $request */
    list($request) = $this->server->getReceivedRequests(true);

    $this->assertRegExp(sprintf(',doc:%s,', self::MYFILE_DOCPATH), (string) $request->getBody());

    $this->assertInstanceOf(Operation\ActionList::className, $actions);
    $this->assertCount(8, $actions);

    /** @var Operation\Action $action */
    $this->assertInstanceOf(Operation\Action::className, $action = $actions[0]);
    $this->assertNotEmpty($action->getId());
    $this->assertNotEmpty($action->getLink());
    $this->assertNotEmpty($action->getIcon());
    $this->assertNotEmpty($action->getLabel());
    $this->assertNotNull($action->getHelp());
  }

  public function testGroupSuggest() {
    $client = new NuxeoClient($this->server->getUrl());

    $this->server->enqueue(array(
      new Response(200, array('Content-Type' => Constants::CONTENT_TYPE_JSON), file_get_contents($this->getResource('usergroup-suggest.json')))
    ));

    $groups = $client->automation('UserGroup.Suggestion')
      ->execute(Operation\UserGroupList::className);

    $this->assertInstanceOf(Operation\UserGroupList::className, $groups);
    $this->assertCount(4, $groups);

    /** @var Operation\UserGroup $group */
    $this->assertInstanceOf(Operation\UserGroup::className, $group = $groups[0]);
    $this->assertNotEmpty($group->getEmail());
    $this->assertNotEmpty($group->getUsername());
    $this->assertNotEmpty($group->getId());
    $this->assertNotEmpty($group->getPrefixedId());
    $this->assertNotEmpty($group->getDisplayLabel());
    $this->assertEquals(Operation\UserGroup::USER_TYPE, $group->getType());

    $this->assertInstanceOf(Operation\UserGroup::className, $group = $groups[1]);
    $this->assertNotEmpty($group->getDescription());
    $this->assertNotEmpty($group->getGroupLabel());
    $this->assertNotEmpty($group->getGroupName());
    $this->assertEquals(Operation\UserGroup::GROUP_TYPE, $group->getType());
  }

}