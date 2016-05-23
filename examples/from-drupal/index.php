<!DOCTYPE html>
<html ng-app="fromDrupal">
  <head>
    <title>Data from Drupal</title>
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.5/angular.min.js"></script>
    <script src="https://code.angularjs.org/1.4.5/angular-sanitize.min.js"></script>
    <script src="js/app.js"></script>
  </head>
  <body ng-cloak>
    <div ng-controller="DrupalController">
      <h1>{{ title }}</h1>
      <p>This node was created on: <strong>{{ drupal.posted }}</strong></p>
      <p>This node was last updated on: <strong>{{ drupal.updated }}</strong></p>
      <div>
        <span ng-bind-html="drupal.body"></span>
      </div>
    </div>

  </body>
</html>
