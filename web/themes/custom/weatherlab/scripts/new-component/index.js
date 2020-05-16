'use strict';
var yeoman = require('yeoman-generator');
var includes = require('lodash.includes');
var path = require('path');
var fs = require('fs');
var plBase = ('./components/_patterns');

module.exports = yeoman.Base.extend({
  prompting: function () {

    console.log('Hi! This will help you build a component folder with assets.');
    console.log('Templates for this are in: ' + path.relative(process.cwd(), __dirname));
    console.log('');

    var prompts = [{
      type: 'list',
      name: 'patternType',
      message: 'Where would you like this new component?',
      choices: fs.readdirSync(plBase, 'utf8')
    }, {
      type: 'list',
      name: 'patternSubType',
      message: 'Where in here?',
      choices: function(answers) {
        var folder = path.join(plBase, answers.patternType);
        var subfolders = fs.readdirSync(folder, 'utf8');
        return ['./'].concat(subfolders);
      }
    }, {
      type: 'list',
      name: 'patternTemplate',
      message: 'What template would you like to create?',
      choices: [
        'Content',
        'Block',
        'Building Block',
        'List',
        'Menu',
        'Region',
        'Default'
      ],
      default: [
        'Default'
      ]
    }, {
      type: 'checkbox',
      name: 'files',
      message: 'What files would you like in there?',
      choices: [
        'twig',
        'scss',
        'js',
        'md',
        'yml'
      ],
      default: [
        'twig',
        'scss',
        'md',
        'yml',
      ]
    }, {
      name: 'name',
      message: 'What shall we name it? (You can use spaces and capitals)',
      filter: function(answer) {
        return answer;
      }
    }];

    return this.prompt(prompts).then(function (props) {
      String.prototype.camelCase = function () {
        return this.replace(/(?:^\w|[A-Z]|\b\w|\s+)/g, function (match, index) {
          if (+match === 0) return ""; // or if (/\s+/.test(match)) for white spaces
          return index == 0 ? match.toLowerCase() : match.toUpperCase();
        });
      };
      // To access props later use this.props.someAnswer;
      props.dashlessName = props.name.camelCase().replace(/-/g, '');
      props.snakeName = props.name.replace(/ /g, '_').toLowerCase();
      props.cleanName = props.name;
      props.name = props.name.replace(/ /g, '-').toLowerCase();
      this.props = props;
    }.bind(this));
  },

  writing: function () {

    // console.log(this.props);
    if (includes(this.props.patternTemplate, 'List')){
      var destPath = path.join(plBase, this.props.patternType, this.props.patternSubType, 'list-' + this.props.name);
    } else {
      var destPath = path.join(plBase, this.props.patternType, this.props.patternSubType, this.props.name);
    }

    if (includes(this.props.files, 'scss') && includes(this.props.patternTemplate, 'List')) {
      this.fs.copyTpl(
        this.templatePath('list/_list.scss'),
        this.destinationPath(path.join(destPath, '_list-' + this.props.name + '.scss')),
        this.props
      );
    } else if (includes(this.props.files, 'scss')) {
      this.fs.copyTpl(
        this.templatePath('_pattern.scss'),
        this.destinationPath(path.join(destPath, '_' + this.props.name + '.scss')),
        this.props
      );
    }

    if (includes(this.props.files, 'twig') && includes(this.props.patternTemplate, 'Content')) {
      this.fs.copyTpl(
        this.templatePath('content/content.twigfile'),
        this.destinationPath(path.join(destPath, this.props.name + '.twig')),
        this.props
      );
    } else if (includes(this.props.files, 'twig') && (this.props.patternTemplate === 'Block')) {
      this.fs.copyTpl(
        this.templatePath('block/_block.twigfile'),
        this.destinationPath(path.join(destPath, '_block.twig')),
        this.props
      );
    } else if (includes(this.props.files, 'twig') && (this.props.patternTemplate === 'Building Block')) {
      this.fs.copyTpl(
        this.templatePath('building-block/building-block.twigfile'),
        this.destinationPath(path.join(destPath, this.props.name + '.twig')),
        this.props
      );
    } else if (includes(this.props.files, 'twig') && includes(this.props.patternTemplate, 'Menu')) {
      this.fs.copyTpl(
        this.templatePath('menu/_menu.twigfile'),
        this.destinationPath(path.join(destPath, '_menu-block.twig')),
        this.props
      );
    } else if (includes(this.props.files, 'twig') && includes(this.props.patternTemplate, 'Region')) {
      this.fs.copyTpl(
        this.templatePath('region/region.twigfile'),
        this.destinationPath(path.join(destPath, this.props.name + '.twig')),
        this.props
      );
    } else if (includes(this.props.files, 'twig') && includes(this.props.patternTemplate, 'List')) {
      this.fs.copyTpl(
        this.templatePath('list/list.twigfile'),
        this.destinationPath(path.join(destPath, '_list.twig')),
        this.props
      );
    } else if (includes(this.props.files, 'twig')) {
      this.fs.copyTpl(
        this.templatePath('pattern.twigfile'),
        this.destinationPath(path.join(destPath, this.props.name + '.twig')),
        this.props
      );
    }

    if (includes(this.props.files, 'json')) {
      this.fs.copyTpl(
        this.templatePath('pattern.json'),
        this.destinationPath(path.join(destPath, this.props.name + '.json')),
        this.props
      );
    }

    if (includes(this.props.files, 'js')) {
      this.fs.copyTpl(
        this.templatePath('pattern.js'),
        this.destinationPath(path.join(destPath, this.props.name + '.js')),
        this.props
      );
    }

    if (includes(this.props.files, 'yml') && includes(this.props.patternTemplate, 'Content')) {
      this.fs.copyTpl(
        this.templatePath('content/content.yml'),
        this.destinationPath(path.join(destPath, this.props.name + '.yml')),
        this.props
      );
    } else if (includes(this.props.files, 'yml') && (this.props.patternTemplate === 'Block')) {
      this.fs.copyTpl(
        this.templatePath('block/block.yml'),
        this.destinationPath(path.join(destPath, 'block~' + this.props.name + '.yml')),
        this.props
      );
    } else if (includes(this.props.files, 'yml') && (this.props.patternTemplate === 'Building Block')) {
      this.fs.copyTpl(
        this.templatePath('building-block/building-block.yml'),
        this.destinationPath(path.join(destPath, this.props.name + '.yml')),
        this.props
      );
    } else if (includes(this.props.files, 'yml') && includes(this.props.patternTemplate, 'Menu')) {
      this.fs.copyTpl(
        this.templatePath('menu/menu.yml'),
        this.destinationPath(path.join(destPath, 'menu-block~' + this.props.name + '.yml')),
        this.props
      );
    } else if (includes(this.props.files, 'yml') && includes(this.props.patternTemplate, 'Region')) {
      this.fs.copyTpl(
        this.templatePath('region/region.yml'),
        this.destinationPath(path.join(destPath, this.props.name + '.yml')),
        this.props
      );
    } else if (includes(this.props.files, 'yml') && includes(this.props.patternTemplate, 'List')) {
      this.fs.copyTpl(
        this.templatePath('list/list.yml'),
        this.destinationPath(path.join(destPath, 'list~' + this.props.name + '.yml')),
        this.props
      );
      this.fs.copyTpl(
        this.templatePath('list/_list-items.yml'),
        this.destinationPath(path.join(destPath, '_list-items~' + this.props.name + '.yml')),
        this.props
      );
    } else if (includes(this.props.files, 'yml')) {
      this.fs.copyTpl(
        this.templatePath('pattern.yml'),
        this.destinationPath(path.join(destPath, this.props.name + '.yml')),
        this.props
      );
    }

    if (includes(this.props.files, 'md') && includes(this.props.patternTemplate, 'List')) {
      this.fs.copyTpl(
        this.templatePath('list/list.md'),
        this.destinationPath(path.join(destPath, 'list-' + this.props.name + '.md')),
        this.props
      );
    } else if (includes(this.props.files, 'md')) {
      this.fs.copyTpl(
        this.templatePath('pattern.md'),
        this.destinationPath(path.join(destPath, this.props.name + '.md')),
        this.props
      );
    }

  }

});
