<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use DOMDocument;
use DOMElement;
use OpenSSLAsymmetricKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

/**
 * A SAML 2.0 identity provider for tests: a self-signed certificate made on
 * the spot, and responses whose assertion is really signed with its key.
 */
final class FakeSamlIdentityProvider
{
    public const string ENTITY_ID = 'https://idp.example.test/saml';

    public const string SSO_URL = 'https://idp.example.test/saml/sso';

    public string $certificate;

    private string $privateKey;

    public function __construct()
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $key instanceof OpenSSLAsymmetricKey || throw new RuntimeException('No RSA key.');
        $csr = openssl_csr_new(['commonName' => 'idp.example.test'], $key);
        $certificate = $csr !== false && ! is_bool($csr) ? openssl_csr_sign($csr, null, $key, 30) : false;
        $certificate !== false || throw new RuntimeException('No certificate.');
        openssl_x509_export($certificate, $pem);
        openssl_pkey_export($key, $privateKey);
        $this->certificate = (string) $pem;
        $this->privateKey = (string) $privateKey;
    }

    /**
     * A base64 SAMLResponse for the given request, as posted to the consumer.
     *
     * @param  array<string, list<string>>  $attributes
     * @param  array{audience?: string, destination?: string, issuer?: string, notOnOrAfter?: int, authnContext?: string, sign?: bool, signingKey?: string}  $overrides
     */
    public function response(string $requestId, string $acsUrl, string $audience, string $nameId, array $attributes, array $overrides = []): string
    {
        $now = time();
        $notOnOrAfter = gmdate('Y-m-d\TH:i:s\Z', $overrides['notOnOrAfter'] ?? $now + 300);
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z', $now);
        $issuer = htmlspecialchars($overrides['issuer'] ?? self::ENTITY_ID, ENT_XML1);
        $destination = htmlspecialchars($overrides['destination'] ?? $acsUrl, ENT_XML1);
        $audience = htmlspecialchars($overrides['audience'] ?? $audience, ENT_XML1);
        $context = htmlspecialchars($overrides['authnContext'] ?? 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport', ENT_XML1);
        $assertionId = '_a'.bin2hex(random_bytes(16));
        $statements = '';

        foreach ($attributes as $name => $values) {
            $statements .= '<saml:Attribute Name="'.htmlspecialchars($name, ENT_XML1).'">';

            foreach ($values as $value) {
                $statements .= '<saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">'.htmlspecialchars($value, ENT_XML1).'</saml:AttributeValue>';
            }

            $statements .= '</saml:Attribute>';
        }

        $xml = <<<XML
            <samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_r{$assertionId}" Version="2.0" IssueInstant="{$issueInstant}" Destination="{$destination}" InResponseTo="{$requestId}">
              <saml:Issuer>{$issuer}</saml:Issuer>
              <samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>
              <saml:Assertion ID="{$assertionId}" Version="2.0" IssueInstant="{$issueInstant}">
                <saml:Issuer>{$issuer}</saml:Issuer>
                <saml:Subject>
                  <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">{$nameId}</saml:NameID>
                  <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                    <saml:SubjectConfirmationData NotOnOrAfter="{$notOnOrAfter}" Recipient="{$destination}" InResponseTo="{$requestId}"/>
                  </saml:SubjectConfirmation>
                </saml:Subject>
                <saml:Conditions NotBefore="{$issueInstant}" NotOnOrAfter="{$notOnOrAfter}">
                  <saml:AudienceRestriction><saml:Audience>{$audience}</saml:Audience></saml:AudienceRestriction>
                </saml:Conditions>
                <saml:AuthnStatement AuthnInstant="{$issueInstant}" SessionIndex="{$assertionId}">
                  <saml:AuthnContext><saml:AuthnContextClassRef>{$context}</saml:AuthnContextClassRef></saml:AuthnContext>
                </saml:AuthnStatement>
                <saml:AttributeStatement>{$statements}</saml:AttributeStatement>
              </saml:Assertion>
            </samlp:Response>
            XML;

        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        $document->loadXML($xml);

        if ($overrides['sign'] ?? true) {
            $this->signAssertion($document, $overrides['signingKey'] ?? $this->privateKey);
        }

        return base64_encode((string) $document->saveXML());
    }

    public function otherKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $key instanceof OpenSSLAsymmetricKey || throw new RuntimeException('No RSA key.');
        openssl_pkey_export($key, $pem);

        return (string) $pem;
    }

    private function signAssertion(DOMDocument $document, string $privateKey): void
    {
        $assertion = $document->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
        $assertion instanceof DOMElement || throw new RuntimeException('No assertion.');
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey);
        $signature = new XMLSecurityDSig;
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference($assertion, XMLSecurityDSig::SHA256, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N], ['id_name' => 'ID', 'overwrite' => false]);
        $signature->sign($key);
        $signature->add509Cert($this->certificate);
        // The schema wants the signature right after the assertion's issuer.
        $signature->insertSignature($assertion, $assertion->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)?->nextSibling);
    }
}
