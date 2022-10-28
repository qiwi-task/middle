<?php
// php 7.4+

/*
Входные данные
В первой строке входного файла INPUT.TXT записаны числа N и M (1 ≤ M, N ≤ 500) – размеры фотографии в пикселях по вертикали и по горизонтали. Следующие N строк содержат по M символов каждая: символ '.' соответствует пустому месту, '#' – элементу постройки.
Выходные данные
В выходной файл OUTPUT.TXT выведите единственное число – количество построек на базе.
*/


class FileServiceTypeIdEnum
{
    public const LOCAL = 1;
}

class FileModeIdEnum {
    public const STRING = 1;
    public const CHAR = 2;

    public const ALL = [
        self::STRING,
        self::CHAR,
    ];
}

class CrawlerServiceTypeIdEnum
{
    public const COORDINATES = 1;
}

class FileServiceFactory
{
    /**
     * @param mixed[] $options
     */
    public function resolveFileService(int $fileServiceTypeId, array $options): FileServiceInterface
    {
        if (FileServiceTypeIdEnum::LOCAL === $fileServiceTypeId) {
            return new LocalFileService($options);
        }
        throw new Exception("fileServiceTypeId: {$fileServiceTypeId} is not found");
    }
}

interface FileServiceInterface
{
    public function getData(): array;
    public function writeData(): void;
    public function setWriteData(ReportInfoInterface $reportInfoCrawler);
}

class LocalFileService implements FileServiceInterface
{
    private int $modeId;
    private array $readData;
    private string $writeData;
    private string $inputPath;
    private string $outputPath;
    private string $fileWriteMode = 'w';
    private string $fileReadMode = 'r';

    public function __construct(array $options)
    {
        if (false === in_array($options['modeId'], FileModeIdEnum::ALL)) {
            throw new Exception("modeId {$options['modeId']} is not found");
        }
        $this->modeId = $options['modeId'];
        $this->inputPath = $options['inputPath'];
        $this->outputPath = $options['outputPath'];
        if (true === isset($options['fileWriteMode'])) {
            $this->fileWriteMode = $options['fileWriteMode'];
        }
        if (true === isset($options['fileReadMode'])) {
            $this->fileReadMode = $options['fileReadMode'];
        }
    }

    public function getData(): array
    {
        try {
            $indexLine = 0;
            foreach ($this->getLines() as $line) {
                $this->modifyDataByModeId($line, $indexLine);
                $indexLine++;
            }
            return $this->readData;
        } catch(Exception $e) {
            throw $e;
        }
    }

    private function getLines() {
        try {
            $fo = fopen($this->inputPath, $this->fileReadMode);
            while ($line = fgets($fo)) {
                yield $line;
            }
            fclose($fo);
        } catch(Exception $e) {
            throw $e;
        }
    }

    private function modifyDataByModeId(string $line, int $indexLine): void
    {
        if (FileModeIdEnum::STRING === $this->modeId) {
            $this->readData[$indexLine] = $line;
        }

        if (FileModeIdEnum::CHAR === $this->modeId) {
            foreach (str_split($line) as $indexChar => $char) {
                $this->readData[$indexLine][$indexChar] = $char;
            }
        }
    }

    public function setWriteData(ReportInfoInterface $reportInfoCrawler)
    {
        $this->writeData = $reportInfoCrawler->getData();
    }

    public function writeData(): void
    {
        try {
            $fp = fopen($this->outputPath, $this->fileWriteMode);
            fwrite($fp, $this->writeData);
        } catch(Exception $e) {
            throw $e;
        }
    }
}

interface ReportInfoInterface{
    public function getData();
}

class ReportInfoCrawler implements ReportInfoInterface
{
    public string $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getData(): string
    {
        return $this->data;
    }
}

interface CrawlerServiceInterface
{
    public function run();
    public function getReport(): ReportInfoInterface;
    public function save(): void;
}

class CrawlerServiceFactory
{
    public function resolveCrawlerService(int $crawlerServiceTypeId, array $options, FileServiceInterface $fileService): CrawlerServiceInterface
    {
        if (CrawlerServiceTypeIdEnum::COORDINATES === $crawlerServiceTypeId) {
            return new CrawlerCoordinatesService($fileService, $options);
        }
        throw new Exception("crawlerServiceTypeId: {$crawlerServiceTypeId} is not found");
    }
}

class CrawlerCoordinatesService implements CrawlerServiceInterface
{
    private FileServiceInterface $fileService;
    private string $targetChar;
    private array $radiusSearchingChildren;

    private array $clearData;
    private array $detectingBuildings;
    private int $buildingCount = 0;

    /**
     * @param mixed[] $options
     */
    public function __construct(FileServiceInterface $fileService, array $options)
    {
        $this->fileService = $fileService;

        if (true === isset($options['targetChar'])) {
            $this->targetChar = $options['targetChar'];
        }
        if (true === isset($options['radiusSearchingChildren'])) {
            $this->radiusSearchingChildren = $options['radiusSearchingChildren'];
        }
    }

    public function run()
    {
        $this->clearData();
        $this->detectingBuildings();
        return $this;
    }

    private function clearData()
    {
        foreach ($this->fileService->getData() as $indexLine => $lineData) {
            foreach ($lineData as $indexChar => $char) {
                $isBuilding = $this->targetChar === $char;
                if (false === $isBuilding) {
                    continue;
                }
                $this->clearData[$indexLine . $indexChar] = ['x' => $indexLine, 'y' => $indexChar];
            }
        }
    }

    private function detectingBuildings()
    {
        $this->buildingCount = 0;
        foreach ($this->clearData as $coordinates) {
            if (true === isset($this->detectingBuildings[$coordinates['x'] . $coordinates['y']])) {
                continue;
            }
            $this->findFullCoordinateBuilding($coordinates['x'], $coordinates['y']);
        }
    }

    private function findFullCoordinateBuilding(int $x, int $y): void
    {
        $this->detectingBuildings[$x.$y] = $x.$y;
        $this->findChildBuilding($x, $y);
        $this->buildingCount++;
    }

    private function findChildBuilding(int $x, int $y): void
    {
        foreach ($this->radiusSearchingChildren as $shiftCoordinate) {

            $x += $shiftCoordinate['x'];
            $y += $shiftCoordinate['y'];

            if (true === isset($this->detectingBuildings[$x . $y])) {
                continue;
            }

            if (isset($this->clearData[$x. $y])) {
                $this->detectingBuildings[$x . $y] = $x . $y;
                $this->findChildBuilding($x, $y);
            }
        }
    }

    public function getReport(): ReportInfoInterface
    {
        return new ReportInfoCrawler((string) $this->buildingCount);
    }

    public function save(): void
    {
        $this->fileService->setWriteData($this->getReport());
        $this->fileService->writeData();
    }
}

// Configs
$configCrawler = [
    'targetChar' => '#',
    'radiusSearchingChildren' => [
        ['x' => 1, 'y' => 0],
        ['x' => 0, 'y' => 1],
        ['x' => -1, 'y' => 0],
        ['x' => 0, 'y' => -1],
    ],
];

$configFileService = [
    'inputPath' => './input.txt',
    'outputPath' => './output.txt',
    'modeId' => FileModeIdEnum::CHAR,
    'fileWriteMode' => 'w',
    'fileReadMode' => 'r',
];

// Excecuting
$fileService = (new FileServiceFactory())->resolveFileService(FileServiceTypeIdEnum::LOCAL, $configFileService);
$crawlerService = (new CrawlerServiceFactory)->resolveCrawlerService(CrawlerServiceTypeIdEnum::COORDINATES, $configCrawler, $fileService);
$crawlerService->run();
$crawlerService->save();
print $crawlerService->getReport()->getData();
