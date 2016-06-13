<?php

namespace Logikos\Application;

/**
 * Purpose of this class is extend it rather than just use the trait directly
 * This will allow you to override methods in the trait if needed.
 * 
 * If you do not need to override trait methods then just use the trait :P
 */
abstract class ModuleDefinition {
  use ModuleDefinitionTrait;
}