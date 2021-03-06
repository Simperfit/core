Feature: Handle properly invalid data submitted to the API
  In order to have robust API
  As a client software developer
  I can send unsupported attributes that will be ignored

  @createSchema
  Scenario: Create a resource
    When I send a "POST" request to "/dummies" with body:
    """
    {
      "name": "Not existing",
      "unsupported": true
    }
    """
    Then the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json"
    And the JSON should be equal to:
    """
    {
      "@context": "/contexts/Dummy",
      "@id": "/dummies/1",
      "@type": "Dummy",
      "description": null,
      "dummy": null,
      "dummyBoolean": null,
      "dummyDate": null,
      "dummyPrice": null,
      "relatedDummy": null,
      "relatedDummies": [],
      "jsonData": [],
      "name_converted": null,
      "name": "Not existing",
      "alias": null
    }
    """

  Scenario: Create a resource with wrong value type for relation
    When I send a "POST" request to "/dummies" with body:
    """
    {
      "name": "Foo",
      "relatedDummy": "1"
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json"
    And the JSON node "@context" should be equal to "/contexts/Error"
    And the JSON node "@type" should be equal to "Error"
    And the JSON node "hydra:title" should be equal to "An error occurred"
    And the JSON node "hydra:description" should be equal to 'Expected IRI or nested object for attribute "relatedDummy" of "ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy", "string" given.'
    And the JSON node "trace" should exist

  Scenario: Ignore invalid dates
    When I send a "POST" request to "/dummies" with body:
    """
    {
      "name": "Invalid date",
      "dummyDate": "Invalid"
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json"

  @dropSchema
  Scenario: Send non-array data when an array is expected
    When I send a "POST" request to "/dummies" with body:
        """
    {
      "name": "Invalid",
      "relatedDummies": "hello"
    }
    """
    Then the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json"
    """
    And the JSON should be equal to:
    {
      "@context": "/contexts/Dummy",
      "@id": "/dummies/2",
      "@type": "Dummy",
      "name": "Invalid",
      "alias": null,
      "description": null,
      "dummyDate": null,
      "dummyPrice": null,
      "jsonData": [],
      "relatedDummy": null,
      "dummy": null,
      "relatedDummies": [],
      "name_converted": null
    }
    """
