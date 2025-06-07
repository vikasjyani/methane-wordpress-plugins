import unittest
import pandas as pd
import os
from kseb_energy_futures_platform.modules.load_profile.file_parser import process_load_curve_template

class TestLoadCurveParser(unittest.TestCase):
    SAMPLE_FILE_DIR = "sample_data" # Relative to tests directory
    SAMPLE_FILE_PATH = os.path.join(SAMPLE_FILE_DIR, "sample_load_curve.xlsx")
    MINIMAL_SAMPLE_FILE_PATH = os.path.join(SAMPLE_FILE_DIR, "minimal_load_curve.xlsx")


    @classmethod
    def setUpClass(cls):
        """Create sample Excel files for testing load curve template parsing."""
        if not os.path.exists(cls.SAMPLE_FILE_DIR):
            os.makedirs(cls.SAMPLE_FILE_DIR)

        try:
            # --- Create Full Sample File ---
            with pd.ExcelWriter(cls.SAMPLE_FILE_PATH, engine='openpyxl') as writer:
                # Past_Hourly_Demand sheet
                hourly_data = {
                    'date': ['2023-01-01', '2023-01-01', '2023-01-01', '2023-01-02'], # Dates as strings
                    'time': ['00:00:00', '01:00:00', '01:30:00', '00:00:00'], # Times as strings
                    'demand': [10.0, 12.5, 6.0, 9.8]
                }
                df_hourly = pd.DataFrame(hourly_data)
                # No conversion needed here as they are already strings. Parser will handle combining.
                df_hourly.to_excel(writer, sheet_name="Past_Hourly_Demand", index=False)

                # Total Demand sheet
                total_demand_data = {
                    'Financial Year': ['2022-23', '2023-24', 'FY 2024-2025'], # Test various year formats
                    'Total Demand GWh': [12000.5, 12550.2, 13000] # Test column name flexibility
                }
                pd.DataFrame(total_demand_data).to_excel(writer, sheet_name="Total Demand", index=False)

                # max_demand sheet
                max_demand_data = {
                    'financial_year': ['2022-23', '2023-24'],
                    'Apr': [2000, 2100], 'May': [2100, 2200], 'Jun': [1900, 2000],
                    'Jul': [1800, 1900], 'Aug': [1850, 1950], 'Sep': [1950, 2050],
                    'Oct': [2050, 2150], 'Nov': [2000, 2100], 'Dec': [1900, 2000],
                    'Jan': [1950, 2050], 'Feb': [1850, 1950], 'Mar': [2000, 2100]
                }
                pd.DataFrame(max_demand_data).to_excel(writer, sheet_name="max_demand", index=False)

                # load_factors sheet
                load_factors_data = {
                    'Year': ['2022-23', '2023-24'], # Test alternative year column name
                    'LF': [0.65, "66%"] # Test alternative LF col name and string percentage
                }
                pd.DataFrame(load_factors_data).to_excel(writer, sheet_name="load_factors", index=False)
            print(f"Created sample file: {cls.SAMPLE_FILE_PATH}")

            # --- Create Minimal Sample File (only required sheets) ---
            with pd.ExcelWriter(cls.MINIMAL_SAMPLE_FILE_PATH, engine='openpyxl') as writer:
                # Past_Hourly_Demand sheet (minimal)
                hourly_data_min = {'date': ['2023-05-10'], 'time': ['14:00:00'], 'demand': [150.75]}
                df_hourly_min = pd.DataFrame(hourly_data_min)
                # No conversion needed here
                df_hourly_min.to_excel(writer, sheet_name="Past_Hourly_Demand", index=False)

                # Total Demand sheet (minimal)
                total_demand_data_min = {'Year': ['2023-24'], 'Annual_Demand': [12800]} # Test other common names
                pd.DataFrame(total_demand_data_min).to_excel(writer, sheet_name="Total Demand", index=False)
            print(f"Created minimal sample file: {cls.MINIMAL_SAMPLE_FILE_PATH}")

        except Exception as e:
            print(f"Error creating sample Excel files for load curve parser: {e}")
            raise

    @classmethod
    def tearDownClass(cls):
        """Remove the sample Excel files after tests."""
        for path in [cls.SAMPLE_FILE_PATH, cls.MINIMAL_SAMPLE_FILE_PATH]:
            if os.path.exists(path):
                os.remove(path)
                print(f"Removed sample file: {path}")
        try:
            if not os.listdir(cls.SAMPLE_FILE_DIR):
                 os.rmdir(cls.SAMPLE_FILE_DIR)
                 print(f"Removed directory: {cls.SAMPLE_FILE_DIR}")
        except OSError:
            print(f"Warning: Could not remove directory {cls.SAMPLE_FILE_DIR}.")


    def test_process_load_curve_template_full_structure(self):
        """Test parsing of a load curve template with all sheets present."""
        parsed_data = process_load_curve_template(self.SAMPLE_FILE_PATH)
        self.assertIsNotNone(parsed_data, "Parser returned None for the full sample file.")

        expected_keys = ['past_hourly_demand', 'total_annual_demand',
                         'monthly_peak_targets', 'annual_load_factor_targets']
        for key in expected_keys:
            self.assertIn(key, parsed_data, f"Key '{key}' missing in parsed data.")

        # Test 'past_hourly_demand'
        phd_df = parsed_data['past_hourly_demand']
        self.assertIsInstance(phd_df, pd.Series) # It's a Series after resample and selecting 'demand'
        self.assertFalse(phd_df.empty)
        self.assertIsInstance(phd_df.index, pd.DatetimeIndex)
        self.assertEqual(phd_df.index.freqstr.lower(), 'h') # Check if resampled to hourly (accept 'h' or 'H')
        # Check specific resampled value: 2023-01-01 00:00:00 had 10.0, 01:00:00 had 12.5, 01:30:00 had 6.0
        # The 00:30 value should be part of 00:00 or 01:00 depending on how resample handles it or if time is string.
        # Given current parser logic: 'date' + 'time' string concat.
        # '2023-01-01 00:00:00': 10
        # '2023-01-01 01:00:00': 12.5
        # '2023-01-01 01:30:00': 6.0 -> this will be its own entry if not careful with string to datetime
        # '2023-01-02 00:00:00': 9.8
        # After resample('H').sum():
        # 2023-01-01 00:00:00 should be 10.0
        # 2023-01-01 01:00:00 should be 12.5 + 6.0 = 18.5
        self.assertEqual(phd_df.loc['2023-01-01 00:00:00'], 10.0)
        self.assertEqual(phd_df.loc['2023-01-01 01:00:00'], 18.5) # 12.5 (at 01:00) + 6.0 (at 01:30)
        self.assertEqual(phd_df.loc['2023-01-02 00:00:00'], 9.8)


        # Test 'total_annual_demand'
        tad_df = parsed_data['total_annual_demand']
        self.assertIsInstance(tad_df, pd.DataFrame)
        self.assertFalse(tad_df.empty)
        self.assertListEqual(list(tad_df.columns), ['financial_year', 'annual_total_demand'])
        self.assertEqual(tad_df.iloc[0]['annual_total_demand'], 12000.5)

        # Test 'monthly_peak_targets'
        mpt_df = parsed_data['monthly_peak_targets']
        self.assertIsInstance(mpt_df, pd.DataFrame)
        self.assertFalse(mpt_df.empty)
        self.assertIn('financial_year', mpt_df.columns)
        self.assertIn('apr', mpt_df.columns) # Normalized to lowercase
        self.assertEqual(mpt_df[mpt_df['financial_year'] == '2022-23'].iloc[0]['apr'], 2000)

        # Test 'annual_load_factor_targets'
        alf_df = parsed_data['annual_load_factor_targets']
        self.assertIsInstance(alf_df, pd.DataFrame)
        self.assertFalse(alf_df.empty)
        self.assertListEqual(list(alf_df.columns), ['financial_year', 'load_factor'])
        self.assertEqual(alf_df[alf_df['financial_year'] == '2022-23'].iloc[0]['load_factor'], 0.65)
        # Test string percentage conversion
        self.assertEqual(alf_df[alf_df['financial_year'] == '2023-24'].iloc[0]['load_factor'], 0.66)


    def test_process_load_curve_template_minimal_structure(self):
        """Test parsing of a load curve template with only required sheets present."""
        parsed_data = process_load_curve_template(self.MINIMAL_SAMPLE_FILE_PATH)
        self.assertIsNotNone(parsed_data, "Parser returned None for the minimal sample file.")

        self.assertIn('past_hourly_demand', parsed_data)
        self.assertFalse(parsed_data['past_hourly_demand'].empty)
        self.assertEqual(parsed_data['past_hourly_demand'].iloc[0], 150.75)


        self.assertIn('total_annual_demand', parsed_data)
        self.assertFalse(parsed_data['total_annual_demand'].empty)
        self.assertEqual(parsed_data['total_annual_demand'].iloc[0]['annual_total_demand'], 12800)

        # Optional sheets should result in empty DataFrames as per parser's current design
        self.assertIn('monthly_peak_targets', parsed_data)
        self.assertTrue(parsed_data['monthly_peak_targets'].empty,
                        f"Monthly peak targets should be empty, but got {len(parsed_data['monthly_peak_targets'])} records")

        self.assertIn('annual_load_factor_targets', parsed_data)
        self.assertTrue(parsed_data['annual_load_factor_targets'].empty,
                        f"Annual load factors should be empty, but got {len(parsed_data['annual_load_factor_targets'])} records")

    def test_missing_file(self):
        """Test behavior when the Excel file is missing."""
        parsed_data = process_load_curve_template("non_existent_file.xlsx")
        self.assertIsNone(parsed_data, "Parser should return None for a missing file.")

    def test_missing_critical_sheet(self):
        """Test behavior if a critical sheet (e.g., 'Past_Hourly_Demand') is missing."""
        # Create a temporary file without 'Past_Hourly_Demand'
        temp_file = os.path.join(self.SAMPLE_FILE_DIR, "temp_missing_sheet.xlsx")
        with pd.ExcelWriter(temp_file, engine='openpyxl') as writer:
            total_demand_data_min = {'Year': ['2023-24'], 'Annual_Demand': [12800]}
            pd.DataFrame(total_demand_data_min).to_excel(writer, sheet_name="Total Demand", index=False)

        parsed_data = process_load_curve_template(temp_file)
        self.assertIsNone(parsed_data, "Parser should return None if 'Past_Hourly_Demand' is missing.")
        os.remove(temp_file)

    def test_malformed_past_hourly_demand(self):
        """Test graceful handling of malformed 'Past_Hourly_Demand' sheet."""
        temp_file = os.path.join(self.SAMPLE_FILE_DIR, "temp_malformed_hourly.xlsx")
        with pd.ExcelWriter(temp_file, engine='openpyxl') as writer:
            # Missing 'demand' column
            hourly_data_bad = {'date': ['2023-01-01'], 'time': ['00:00:00'], 'wrong_column': [100]}
            pd.DataFrame(hourly_data_bad).to_excel(writer, sheet_name="Past_Hourly_Demand", index=False)
            total_demand_data_min = {'Year': ['2023-24'], 'Annual_Demand': [12800]}
            pd.DataFrame(total_demand_data_min).to_excel(writer, sheet_name="Total Demand", index=False)

        parsed_data = process_load_curve_template(temp_file)
        # Depending on parser's strictness, this might return None or proceed with empty hourly data
        self.assertIsNone(parsed_data, "Parser should return None if critical columns in 'Past_Hourly_Demand' are missing.")
        os.remove(temp_file)


if __name__ == '__main__':
    unittest.main()
