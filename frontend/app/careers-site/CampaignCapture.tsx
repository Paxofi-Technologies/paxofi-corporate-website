"use client";

import { useEffect } from "react";
import { CAMPAIGN_STORAGE_KEY, campaignFrom } from "@/lib/careers";

/**
 * Remembers the campaign tags of the link a candidate arrived on (P3.1), for
 * this browser tab only, so an application made a few pages later still says
 * which campaign brought them. Nothing is stored if the link has no tags.
 */
export default function CampaignCapture() {
  useEffect(() => {
    const campaign = campaignFrom(window.location.search);
    if (!campaign) return;
    try {
      sessionStorage.setItem(CAMPAIGN_STORAGE_KEY, JSON.stringify(campaign));
    } catch {
      // Storage blocked: the application simply has no campaign.
    }
  }, []);
  return null;
}
