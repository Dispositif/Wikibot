<?php


namespace App\Domain\Enums;

// todo legacy

/**
 * Class LanguageEnum
 * new LanguageEnum(LanguageEnum::FROM_LANG,'fr');
 *
 * @method static self FROM_LANG()
 */
class LanguageEnum extends Enum
{
    /**
     * __callStatic()
     */
    public const FROM_LANG = 'fromlang';

    private $origin;
    /**
     * Default frwiki lang
     *
     * @var string
     */
    private $frlang;

    /**
     * LanguageEnum constructor.
     *
     * @param string $origin
     * @param string $valueLanguage
     *
     * @throws \Exception
     */
    public function __construct(
        string $origin,
        string $valueLanguage
    ) {
        if ($origin === 'fromlang') {
            $this->frlang = $valueLanguage;
            $this->origin = $origin;

            return;
        }
        throw new \Exception('No language origin constant');
    }

    public function getFrLang()
    {
        return $this->frlang;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getFrLangText():string
    {
        // todo Refactor
        require_once 'languageData.php';
        if (!array_key_exists($this->frlang, $frlang_to_french)) {
            throw new \Exception('unknow language '.$this->frlang);
        }

        return $frlang_to_french[$this->frlang];
    }

}
