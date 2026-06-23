# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [0.2.0]
### Changed
- Variable names now accept internal whitespace: `{{ My first column }}` parses as the variable `My first column`. Runs of whitespace inside braces are collapsed to single spaces. Block-tag arguments (`#if`/`#unless`/`#each`) remain strict — spaces there are still rejected.
- Stricter identifier validation. Names with leading, trailing, or consecutive dots (`{{.a}}`, `{{a.}}`, `{{a..b}}`) and segments that begin with a digit after a space (`{{first 2name}}`) are now rejected; previously they were silently accepted.

### Internal
- Replaced the regex-based tag scanner with a character-level `Tokenizer`, extracted from `Parser`.
- New `Name` and `TagSource` value objects own identifier validation and tag-error formatting.
- `BlockKind` owns open-token → AST-node construction and the keyword list used in error messages, removing the `match` in `Parser::parseNodes`.

## [0.1.1]
- Adds first version
