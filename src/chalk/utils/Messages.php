<?php

/*
 * Copyright 2015 ChalkPE
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

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-18 18:44
 * @copyright Apache-v2.0
 */

namespace chalk\utils;

class Messages {
    /** @var int */
    private int $version;

    /** @var string */
    private string $defaultLanguage;

    /** @var array */
    private array $messages;

    /**
     * @param array $config
     */
    public function __construct(array $config){
        $this->version = isset($config["default-language"]) && is_int($config["default-language"]) ? $config["default-language"] : 0;
        $this->defaultLanguage = isset($config["default-language"]) && is_string($config["default-language"]) ? $config["default-language"] : "en";
        $this->messages = isset($config["messages"]) && is_array($config["messages"]) ? $config["messages"] : [];
    }

    /**
     * @return int
     */
    public function getVersion(): int {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getDefaultLanguage(): string {
        return $this->defaultLanguage;
    }

    /**
     * @return array
     */
    public function getMessages(): array {
        return $this->messages;
    }

    /**
     * @param string $key
     * @param string[] $format
     * @param string $language
     * @return string|null
     */
    public function getMessage(string $key, array $format = [], string $language = ""): ?string {
        if ($language === "") {
            $language = $this->getDefaultLanguage();
        }

        if (!isset($this->messages[$key])) {
            return null;
        }

        $message = $this->messages[$key];
        $string = $message[$language] ?? $message[$this->getDefaultLanguage()] ?? null;

        if ($string !== null) {
            foreach ($format as $key => $value) {
                $string = str_replace("{%" . $key . "}", $value, $string);
            }
            return $string;
        }

        return null;
    }
}
