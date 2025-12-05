import { TRADING_PLATFORMS, ORGANIZATIONS } from './constants';

/**
 * Определяет торговую площадку по URL
 * @param url URL ссылки на торги
 * @returns Объект торговой площадки или null
 */
export function detectTradingPlatform(url: string) {
  if (!url) return null;
  
  for (const platform of TRADING_PLATFORMS) {
    if (platform.urlPattern && platform.urlPattern.test(url)) {
      return platform;
    }
  }
  
  return TRADING_PLATFORMS.find(p => p.id === 'other') || null;
}

export function formatBankDetails(orgId: string, selectedBankId?: string | null): string {
  const org = ORGANIZATIONS[orgId as keyof typeof ORGANIZATIONS];
  if (!org) return '';

  let bank = null;
  if (org.bankAccounts && org.bankAccounts.length > 0) {
    if (selectedBankId) {
      bank = org.bankAccounts.find(b => b.id === selectedBankId);
    }
    if (!bank) {
      bank = org.bankAccounts[0]; // Default to first
    }
  }

  if (bank && bank.details && bank.details.ru) {
    return bank.details.ru;
  }
  
  // Fallback (although our constant has bankAccounts)
  const parts = [];
  const anyOrg = org as any;
  if (anyOrg.bankName) parts.push(`Банк: ${anyOrg.bankName}`);
  if (anyOrg.bankAddress) parts.push(`Адрес банка: ${anyOrg.bankAddress}`);
  if (anyOrg.account) parts.push(`Счет: ${anyOrg.account}`);
  if (anyOrg.bik) parts.push(`БИК: ${anyOrg.bik}`);
  if (anyOrg.correspondentAccount) parts.push(`Корр. счет: ${anyOrg.correspondentAccount}`);
  if (anyOrg.beneficiary) parts.push(`Бенефициар: ${anyOrg.beneficiary}`);
  
  return parts.join('\n');
}

