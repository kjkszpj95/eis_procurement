<?php

namespace app\commands;

use yii\console\Controller;
use yii\helpers\Console;
use Shuchkin\SimpleXLSX;
use Yii;
use app\models\LegalEntity;
class ParseController extends Controller
{

    public $url = 'https://zakupki.gov.ru/epz/main/public/document/view.html?sectionId=2369';
    public $base_url = 'https://zakupki.gov.ru';

    protected function fetchUrl($url)
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1,
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Connection: keep-alive',
                ],
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }

    public function actionSaveData()
    {
        $data = $this->actionGetXlsx();
        if (!$data) {
            $this->stderr("Нет данных для сохранения.\n");
            return self::EXIT_CODE_ERROR;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($data as $row) {
                $model = new LegalEntity();
                $model->attributes = $row;
                if (!$model->save()) {
                    throw new \Exception('Ошибка сохранения записи: ' . print_r($model->errors, true));
                }
            }
            $transaction->commit();
            $this->stdout("Успешно сохранено " . count($data) . " записей.\n", Console::FG_GREEN);
            return self::EXIT_CODE_NORMAL;
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->stderr("Ошибка при сохранении: " . $e->getMessage() . "\n");
            Yii::error($e->getMessage());
            return self::EXIT_CODE_ERROR;
        }
    }
    protected function actionGetXlsx()
    {
        $fileUrl = $this->actionGetHref();
        if (!$fileUrl || !is_string($fileUrl)) {
            $this->stderr("Ссылка на XLSX не найдена.\n");
            return null;
        }

        $content = $this->fetchUrl($fileUrl);
        if ($content === false) {
            $this->stderr("Не удалось скачать файл.\n");
            return null;
        }

        $rows = $this->parseXlsxContent($content);
        if ($rows === null) {
            return null;
        }

        return $this->mapRowsToEntities($rows);
    }

        protected function parseXlsxContent($content)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'zakupki_xlsx_');
        file_put_contents($tempFile, $content);

        if (!SimpleXLSX::parse($tempFile)) {
            $this->stderr("Ошибка чтения XLSX: " . SimpleXLSX::parseError() . "\n");
            @unlink($tempFile);
            return null;
        }

        $xlsx = SimpleXLSX::parse($tempFile);
        $rows = $xlsx->rows();
        @unlink($tempFile);

        return $rows;
    }

    protected function mapRowsToEntities($rows)
    {
        if (empty($rows)) {
            return [];
        }

        $headerToAttribute = LegalEntity::getHeaderToAttributeMap();
        $headers = array_map('trim', $rows[0]);
        $colMap = $this->buildColumnMap($headers, $headerToAttribute);
        $data = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn($cell) => trim((string)$cell) !== ''))) {
                continue;
            }

            $record = $this->mapRowToRecord($row, $colMap);
            $data[] = $record;
        }

        return $data;
    }
    protected function buildColumnMap($headers, $headerToAttribute)
    {
        $colMap = [];
        foreach ($headers as $colIndex => $header) {
            $header = trim((string)$header);
            if (isset($headerToAttribute[$header])) {
                $colMap[$colIndex] = $headerToAttribute[$header];
            } elseif (stripos($header, 'вступления в законную силу') !== false) {
                $colMap[$colIndex] = 'effective_date';
            }
        }
        return $colMap;
    }

    protected function mapRowToRecord($row, $colMap)
    {
        $record = [];
        foreach ($row as $colIndex => $cell) {
            if (isset($colMap[$colIndex])) {
                $attr = $colMap[$colIndex];
                $value = trim((string)$cell);

                if (in_array($attr, ['decision_date', 'effective_date']) && $value) {
                    $date = \DateTime::createFromFormat('d.m.Y', $value);
                    $record[$attr] = $date ? $date->format('Y-m-d') : null;
                } else {
                    $record[$attr] = $value ?: null;
                }
            }
        }
        return $record;
    }

    protected function actionGetHref($inputUrl = null)
    {
        $url = $inputUrl ?? $this->url;
        $this->stdout("Загрузка страницы: $url\n", Console::FG_YELLOW);

        $html = $this->fetchUrl($url);
        if ($html === false) {
            $this->stderr("Не удалось загрузить страницу.\n");
            return null;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        foreach ($doc->getElementsByTagName('a') as $el) {
            $class = $el->getAttribute('class');
            if (
                preg_match('/\bdocs-title\b/', $class) &&
                preg_match('/\bheading-h4\b/', $class)
            ) {
                $href = $el->getAttribute('href');
                if ($href === '') {
                    $fullUrl = rtrim($this->base_url, '/');
                } elseif (preg_match('~^https?://~i', $href)) {
                    $fullUrl = $href;
                } else {
                    $fullUrl = rtrim($this->base_url, '/') . '/' . ltrim($href, '/');
                }

                $text = trim($el->textContent);
                $this->stdout("Текст: $text\n");
                $this->stdout("Ссылка: $fullUrl\n");
                return $fullUrl;
            }
        }

        $this->stdout(" Ссылка с классами 'docs-title heading-h4' не найдена.\n", Console::FG_YELLOW);
        return null;
    }
}