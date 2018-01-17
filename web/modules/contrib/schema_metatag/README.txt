Schema.org Metatag

This project extends Drupal's Metatag module to display structured data as JSON LD in the head of web pages. Either hard-code properties or identify patterns using token replacements. Using the override system in Metatag module you can define default structured data values for all content types, override the global content defaults for a particular content type, or even override everything else on an individual node to provide specific values for that node.

This module defines metatag groups that map to Schema.org types, and metatag tags for Schema.org properties, then steps in before the values are rendered as metatags, pulls the Schema.org values out of the header created by Metatag, and instead renders them as JSON LD when the page is displayed.

Since the Schema.org list is huge, and growing, this module only provides a small subset of those values. But it is designed to be extensible. There is an included module, Schema.org Article Example, that shows how other modules can add more properties to types that are already defined. Several types are included which can be copied to add new types (groups) with any number of their own properties.

The module includes a base group class and several base tag classes that can be extended. Many properties are simple key/value pairs that require nothing more than extending the base class and giving them their own ids. Some are more complex, like Person and Organization, and BreadcrumbList, and they collect multiple values and serialize the results.

The module creates the following Schema.org object types:

Schema.org/Article
Schema.org/Organization
Schema.org/Event
Schema.org/WebSite
Schema.org/WebPage
Schema.org/ItemList (for Views)
Schema.org/BreadcrumbList

For more information and to test the results:
- https://developers.google.com/search/docs/guides/intro-structured-data
- https://schema.org/docs/full.html
- https://search.google.com/structured-data/testing-tool

For instance, the code in the head might end up looking like this:

<code>
<script type="application/ld+json">{
    "@context": "http://schema.org",
    "@graph": [
        {
            "@type": "Article",
            "description": "Curabitur arcu erat, accumsan id imperdiet et, porttitor at sem. Donec sollicitudin molestie malesuada. Donec sollicitudin molestie malesuada. Donec rutrum congue leo eget malesuada. Nulla quis lorem ut libero malesuada feugiat. Vestibulum ac diam sit amet quam vehicula elementum sed sit amet dui.",
            "datePublished": "2009-11-30T13:04:01-0600",
            "dateModified": "2017-05-17T19:02:01-0500",
            "headline": "Curabitur arcu erat]",
            "author": {
                "@type": "Person",
                "name": "Minney Mouse",
                "sameAs": "https://example.com/user/2"
            },
            "publisher": {
                "@type": "Organization",
                "name": "Example.com",
                "sameAs": "https://example.com/",
                "logo": {
                    "@type": "ImageObject",
                    "url": "https://example.com/sites/default/files/logo.png",
                    "width": "600",
                    "height": "60"
                }
            },
            "mainEntityOfPage": {
                "@type": "WebPage",
                "@id": "https://example.com/story/example-story"
            },
        },
    ]
}</script>
</code>

A new option is an option to "Pivot" multiple values for the Person, Organization, Address, or Offer. If selected, this will change the result from:

<code>
<script type="application/ld+json">{
    "@context": "http://schema.org",
    "@graph": [
        {
            "@type": "Event",
            "name": "Premier",
            "url": "example.com/event/premier",
            "description": "Lorem ipsum dolor sit amet, consectetur.",
            "offers": [
                {
                    "@type": "Offer",
                    "price": [
                      "10",
                      "20",
                    ],
                    "priceCurrency": "USD",
                    "url": "http://amazon.com"
                },
            ],
            "actor": {
                "@type": "Person",
                "name": [
                    "Micky Mouse",
                    "Donald Duck",
                    "Tweety Bird"
                ],
                "url": [
                    "http://example.com/person/mickey-mouse",
                    "http://example.com/person/donald-duck",
                    "http://example.com/person/tweety-bird"
                ],
            }
        },
    ]
}</script>
</code>

to:

<code>
<script type="application/ld+json">{
    "@context": "http://schema.org",
    "@graph": [
        {
            "@type": "Event",
            "name": "Premier",
            "url": "example.com/event/premier",
            "description": "Lorem ipsum dolor sit amet, consectetur.",
            "offers": [
                {
                    "@type": "Offer",
                    "price": "10",
                    "priceCurrency": "USD",
                    "url": "http://amazon.com"
                },
                {
                    "@type": "Offer",
                    "price": "20",
                    "priceCurrency": "USD",
                    "url": "http://amazon.com"
                }
            ],
            "actor": {
                {
                    "@type": "Person",
                    "name": "Mickey Mouse",
                    "url": "http://example.com/person/mickey-mouse",
                },
                {
                    "@type": "Person",
                    "name": "Daffy Duck",
                    "url": "http://example.com/person/daffy-duck",
                },
                {
                    "@type": "Person",
                    "name": "Tweety Bird
                    "url": "http://example.com/person/tweety-bird",
                },
            }
        },
    ]
}</script>
</code>

