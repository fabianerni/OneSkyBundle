<?php

namespace OpenClassrooms\Bundle\OneSkyBundle\Services\Impl;

use OpenClassrooms\Bundle\OneSkyBundle\EventListener\TranslationPostPullEvent;
use OpenClassrooms\Bundle\OneSkyBundle\EventListener\TranslationPostPushEvent;
use OpenClassrooms\Bundle\OneSkyBundle\EventListener\TranslationPrePullEvent;
use OpenClassrooms\Bundle\OneSkyBundle\EventListener\TranslationPrePushEvent;
use OpenClassrooms\Bundle\OneSkyBundle\EventListener\TranslationUpdateEvent;
use OpenClassrooms\Bundle\OneSkyBundle\Model\FileFactory;
use OpenClassrooms\Bundle\OneSkyBundle\Services\FileService;
use OpenClassrooms\Bundle\OneSkyBundle\Services\TranslationService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @author Romain Kuzniak <romain.kuzniak@openclassrooms.com>
 */
class TranslationServiceImpl implements TranslationService
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string[]
     */
    private $filePaths;

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var string
     */
    private $fileFormat;

    /**
     * @var FileService
     */
    private $fileService;

    /**
     * @var string[]
     */
    private $requestedLocales;

    /**
     * @var string
     */
    private $sourceLocale;

    /**
     * {@inheritdoc}
     */
    public function update(array $filePaths = [], array $locales = [])
    {
        $this->eventDispatcher->dispatch(TranslationUpdateEvent::getEventName(), new TranslationUpdateEvent());

        return [$this->pull($filePaths, $locales), $this->push($filePaths)];
    }

    /**
     * {@inheritdoc}
     */
    public function pull(array $filePaths, array $locales = [])
    {
        $exportFiles = [];
        /** @var SplFileInfo $file */
        foreach ($this->getFiles($filePaths) as $file) {
            foreach ($this->getRequestedLocales($locales) as $locale) {
                $exportFiles[] = $this->fileFactory->createExportFile($file->getRealpath(), $locale);
            }
        }

        $this->eventDispatcher->dispatch(
            TranslationPrePullEvent::getEventName(),
            new TranslationPrePullEvent($exportFiles)
        );

        $downloadedFiles = $this->fileService->download($exportFiles);

        $this->eventDispatcher->dispatch(
            TranslationPostPullEvent::getEventName(),
            new TranslationPostPullEvent($downloadedFiles)
        );

        return $downloadedFiles;
    }

    /**
     * @return Finder
     */
    private function getFiles(array $filePaths)
    {
        return Finder::create()
            ->files()
            ->in($this->getFilePaths($filePaths))
            ->name('*.'.$this->sourceLocale.'.'.$this->fileFormat);
    }

    /**
     * @return string[]
     */
    private function getFilePaths(array $filePaths)
    {
        return empty($filePaths) ? $this->filePaths : $filePaths;
    }

    /**
     * @return string[]
     */
    private function getRequestedLocales(array $locales)
    {
        return empty($locales) ? $this->requestedLocales : $locales;
    }

    /**
     * {@inheritdoc}
     */
    public function push(array $filePaths, array $locales = [])
    {
        $uploadFiles = [];
        /** @var SplFileInfo $file */
        foreach ($this->getFiles($filePaths) as $file) {
            foreach ($this->getSourceLocales($locales) as $locale) {
                $uploadFiles[] = $this->fileFactory->createUploadFile($file->getRealpath(), $locale);
            }
        }

        $this->eventDispatcher->dispatch(
            TranslationPrePushEvent::getEventName(),
            new TranslationPrePushEvent($uploadFiles)
        );

        $uploadedFiles = $this->fileService->upload($uploadFiles);

        $this->eventDispatcher->dispatch(
            TranslationPostPushEvent::getEventName(),
            new TranslationPostPushEvent($uploadedFiles)
        );

        return $uploadedFiles;
    }

    /**
     * @return string[]
     */
    private function getSourceLocales(array $locales)
    {
        return empty($locales) ? [$this->sourceLocale] : $locales;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setFileFactory(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    public function setFileFormat($fileFormat)
    {
        $this->fileFormat = $fileFormat;
    }

    public function setFileService(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    public function setRequestedLocales(array $requestedLocales)
    {
        $this->requestedLocales = $requestedLocales;
    }

    public function setSourceLocale($sourceLocale)
    {
        $this->sourceLocale = $sourceLocale;
    }
}
