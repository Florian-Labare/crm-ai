import React, { useState } from 'react';

interface Tab {
  id: string;
  label: string;
  icon: React.ReactNode;
  content: React.ReactNode;
}

interface VuexyTabsProps {
  tabs: Tab[];
  defaultTab?: string;
  onTabChange?: (tabId: string) => void;
}

export const VuexyTabs: React.FC<VuexyTabsProps> = ({ tabs, defaultTab, onTabChange }) => {
  const [activeTab, setActiveTab] = useState(defaultTab || tabs[0]?.id);

  const handleTabChange = (tabId: string) => {
    setActiveTab(tabId);
    if (onTabChange) {
      onTabChange(tabId);
    }
  };

  const activeTabContent = tabs.find((tab) => tab.id === activeTab)?.content;

  return (
    <div className="vx-card p-0 overflow-hidden vx-fade-in">
      {/* Tab List */}
      <div className="flex border-b-2 border-[#EBE9F1] px-8">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => handleTabChange(tab.id)}
            className={`
              relative px-7 py-5 font-semibold text-[15px] transition-colors duration-200
              flex items-center gap-2
              ${
                activeTab === tab.id
                  ? 'text-[#7367F0]'
                  : 'text-[#6E6B7B] hover:text-[#7367F0]'
              }
            `}
          >
            {tab.icon}
            {tab.label}
            {activeTab === tab.id && (
              <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-[#7367F0]" />
            )}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      <div className="p-8">{activeTabContent}</div>
    </div>
  );
};
