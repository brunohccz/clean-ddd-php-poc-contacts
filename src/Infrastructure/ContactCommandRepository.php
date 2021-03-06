<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Entity\Contact;
use App\Domain\Repository\ContactCommandRepositoryInterface;
use App\Domain\ValueObject\ContactId;
use App\Domain\ValueObject\Nickname;
use App\Domain\ValueObject\PersonName;
use App\Domain\ValueObject\PhoneNumber;
use JsonException;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use RuntimeException;

class ContactCommandRepository implements ContactCommandRepositoryInterface
{
    private Filesystem $contactsDir;
    private array $contacts;

    public function __construct()
    {
        $this->contactsDir = new Filesystem(
            new Local($_ENV["CONTACTS_DIR"])
        );
        $this->contacts = (new ContactQueryRepository())->getContacts();
    }

    public function addContact(
        PersonName $name,
        Nickname $nickname,
        PhoneNumber $phoneNumber
    ): void
    {
        $newContactId = 1;
        if (count($this->contacts) > 0) {
            $newContactId = end($this->contacts)["id"] + 1;
        }

        try {
            $contactData = json_encode(new Contact(
                new ContactId($newContactId),
                $name,
                $nickname,
                $phoneNumber
            ), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Contact data is invalid.');
        }

        $contactFileName = $newContactId . '.contact';
        $this->contactsDir->put($contactFileName, $contactData);
    }

    public function removeContact(ContactId $contactId): void
    {
        $contactFileName = $contactId->getId() . '.contact';
        try {
            $this->contactsDir->delete($contactFileName);
        } catch (FileNotFoundException $e) {
            if ($this->contactsDir->has($contactFileName)) {
                throw new RuntimeException('Unable to delete contact.');
            }
        }
    }

    public function updateContact(Contact $updatedContact): void
    {
        $contactFileName = $updatedContact->getId()->getId() . '.contact';

        try {
            $contactData = json_encode($updatedContact, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Contact data is invalid.');
        }

        try {
            $this->contactsDir->update($contactFileName, $contactData);
        } catch (FileNotFoundException $e) {
            throw new RuntimeException('Contact does not exist.');
        }
    }
}
