(function() {
  var app = angular.module('fromDrupal', ['ngSanitize']);

  app.controller('DrupalController', function($scope, $http) {
    $http.get("http://portumnachamber.com/node/226/output.json")
    .success(function(data) {
      $scope.drupal = data.nodes[0].node;
      $scope.title = data.nodes[0].node.title;
    });
  });

})();
